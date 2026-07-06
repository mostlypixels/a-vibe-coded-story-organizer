<?php

namespace App\Services;

use App\Models\CodexAttribute;
use App\Models\CodexAttributeValue;
use App\Models\CodexEntry;
use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Resolves and mutates the start-anchored step function for a single
 * (entry, attribute) pair.
 *
 * Each codex_attribute_values row means "from this event onward, the value is X".
 * A period is implicit: it runs from its start event until the next anchor (or the
 * project's End). We deliberately do not store an end event, so the periods tile the
 * timeline with no holes or overlaps by construction. See
 * .specs/codex/expanded/attribute-timeline.md for the full rationale.
 *
 * This is the project's first app/Services class: the resolution logic is non-trivial,
 * reusable (model helpers, controllers in later tasks, the seeder) and must stay outside
 * booted() hooks because the seeder runs WithoutModelEvents.
 */
class AttributeTimeline
{
    /**
     * The pair's values in canonical order, memoised for the life of the instance.
     * Reset to null whenever this service writes, so the next read re-queries.
     */
    private ?Collection $values = null;

    public function __construct(
        private readonly CodexEntry $entry,
        private readonly CodexAttribute $attribute,
    ) {}

    /**
     * The pair's values in chronological order, each decorated (display only) with the
     * event that ends its period: the next anchor, or the project's End for the last one.
     */
    public function periods(): Collection
    {
        $values = $this->orderedValues();
        $endEvent = $this->endEvent();

        return $values->map(function (CodexAttributeValue $value, int $index) use ($values, $endEvent) {
            $next = $values->get($index + 1);

            // setRelation attaches endEvent without marking the model dirty, so this
            // display decoration can never be accidentally persisted.
            $value->setRelation('endEvent', $next?->startEvent ?? $endEvent);

            return $value;
        });
    }

    /**
     * Resolve the value in effect at a moment.
     *
     * When passed an Event, the anchor-identity check wins first: if the event is itself
     * an anchor for this pair, its value is returned outright (a scene "during Halloween"
     * sees the Halloween value even if another event shares Halloween's datetime). Only a
     * non-anchor event falls back to the "greatest anchor whose datetime <= t" lookup.
     */
    public function valueAt(CarbonInterface|Event $moment): ?CodexAttributeValue
    {
        $values = $this->orderedValues();

        if ($moment instanceof Event) {
            $anchored = $values->firstWhere('start_event_id', $moment->id);

            if ($anchored !== null) {
                return $anchored;
            }

            $moment = $moment->event_datetime;
        }

        // Values are in canonical (event_datetime, events.id) order, so the last one whose
        // start datetime is <= the moment is the greatest anchor <= the moment.
        return $values->last(
            fn (CodexAttributeValue $value) => $value->startEvent->event_datetime <= $moment
        );
    }

    /**
     * Ensure the pair has a Start-anchored baseline, creating it (with the given value)
     * only when none exists. Idempotent: never produces a duplicate Start row. Called on
     * first edit and by the seeder — hence a plain method, not a booted() hook.
     */
    public function ensureBaseline(string $value = ''): CodexAttributeValue
    {
        $baseline = $this->entry->attributeValues()->firstOrCreate(
            [
                'codex_attribute_id' => $this->attribute->id,
                'start_event_id' => $this->startEvent()->id,
            ],
            ['value' => $value],
        );

        $this->values = null;

        return $baseline;
    }

    /**
     * Insert a period at the given anchor or update the existing one's value. This single
     * upsert backs both "add a period" and "edit an existing period's value" — the DB
     * unique key on (entry, attribute, start_event) is only a backstop.
     */
    public function upsertAt(Event $startEvent, string $value): CodexAttributeValue
    {
        return DB::transaction(function () use ($startEvent, $value) {
            // Invariant: every valued pair has a Start-anchored baseline, so valueAt()
            // is total for t >= Start. Adding a mid-timeline period to a pair that was
            // never valued must not open a hole before the new anchor — ensure the '' baseline
            // first. When the anchor IS Start, this upsert already is the baseline write, so
            // skip ensureBaseline (calling it first would pin '' before updateOrCreate could
            // overwrite it — a wasteful double write).
            if ($startEvent->id !== $this->entry->project->startEvent()->id) {
                $this->ensureBaseline();
            }

            $period = $this->entry->attributeValues()->updateOrCreate(
                [
                    'codex_attribute_id' => $this->attribute->id,
                    'start_event_id' => $startEvent->id,
                ],
                ['value' => $value],
            );

            $this->values = null;

            return $period;
        });
    }

    /**
     * Whether the period anchored at the given event may be removed. Removing the Start
     * baseline while other values exist would open a hole at the beginning of the timeline
     * (invariant #1); it is allowed only when it is the sole value. Exposed so the controller
     * can turn a disallowed request into a 403 (matching the is_main / is_fixed guard style)
     * before calling removeAt.
     */
    public function canRemoveAt(Event $startEvent): bool
    {
        $isStartBaseline = $startEvent->id === $this->startEvent()->id;

        return ! ($isStartBaseline && $this->orderedValues()->count() > 1);
    }

    /**
     * Delete the period anchored at the given event. Refuses to remove the Start baseline
     * while other values exist — that would open a hole at the beginning of the timeline
     * (invariant #1) — allowing it only when it is the sole value.
     */
    public function removeAt(Event $startEvent): void
    {
        if (! $this->canRemoveAt($startEvent)) {
            throw new RuntimeException('The Start baseline cannot be removed while other values exist.');
        }

        $this->entry->attributeValues()
            ->where('codex_attribute_id', $this->attribute->id)
            ->where('start_event_id', $startEvent->id)
            ->delete();

        $this->values = null;
    }

    /**
     * The pair's values in canonical (event_datetime, events.id) order.
     *
     * Fast path: when the entry's attributeValues (with their startEvent) are already
     * eager-loaded — as the "as of" panels and the edit page do to resolve many pairs at
     * once — reuse them and apply the canonical order in memory, so we don't issue one
     * query per (entry, attribute) pair (avoids N+1). This path is read-only; the write
     * methods reset $this->values, and every caller that loads the relation only reads.
     *
     * Otherwise a single query does the ordering: the join on events exists solely to sort
     * by the anchor's datetime then id; models are hydrated from codex_attribute_values.*
     * and startEvent is eager-loaded, so the join columns never collide with the model.
     */
    private function orderedValues(): Collection
    {
        if ($this->values !== null) {
            return $this->values;
        }

        if ($this->entry->relationLoaded('attributeValues')) {
            return $this->values = $this->entry->attributeValues
                ->where('codex_attribute_id', $this->attribute->id)
                ->sortBy(fn (CodexAttributeValue $value) => [
                    $value->startEvent->event_datetime->timestamp,
                    $value->startEvent->id,
                ])
                ->values();
        }

        return $this->values = $this->entry->attributeValues()
            ->where('codex_attribute_id', $this->attribute->id)
            ->join('events', 'events.id', '=', 'codex_attribute_values.start_event_id')
            ->orderBy('events.event_datetime')
            ->orderBy('events.id')
            ->select('codex_attribute_values.*')
            ->with('startEvent')
            ->get();
    }

    /**
     * The project's Start event. Thin delegate to the single source of truth on
     * Project so the (event_datetime, id) query lives in exactly one place.
     */
    private function startEvent(): Event
    {
        return $this->entry->project->startEvent();
    }

    /**
     * The project's End event. Thin delegate to Project::endEvent().
     */
    private function endEvent(): Event
    {
        return $this->entry->project->endEvent();
    }
}
