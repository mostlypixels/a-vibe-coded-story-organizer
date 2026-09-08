<?php

namespace App\Services;

use App\Models\CodexAttribute;
use App\Models\CodexAttributeValue;
use App\Models\CodexEntry;
use App\Models\Event;
use Illuminate\Support\Collection;

/**
 * Wraps each of an entry's attributes in its {@see AttributeTimeline}, split
 * into the Start-anchored baseline and the remaining periods.
 */
class CodexAttributeSheets
{
    /**
     * Every attribute the entry's type defines, including one with no value.
     *
     * @return Collection<int, array{attribute: CodexAttribute, baseline: ?CodexAttributeValue, periods: Collection}>
     */
    private function forEntry(CodexEntry $entry, Event $startEvent): Collection
    {
        return $entry->project->codexAttributesFor($entry->type)
            ->map(fn (CodexAttribute $attribute) => $this->sheetFor($entry, $attribute, $startEvent));
    }

    /**
     * Only attributes attached to the entry — a baseline row or at least one period,
     * blank values included. Row presence, not content, decides "attached".
     *
     * @return Collection<int, array{attribute: CodexAttribute, baseline: ?CodexAttributeValue, periods: Collection}>
     */
    public function attached(CodexEntry $entry, Event $startEvent): Collection
    {
        return $this->forEntry($entry, $startEvent)
            ->reject(fn (array $sheet) => $sheet['baseline'] === null && $sheet['periods']->isEmpty())
            ->values();
    }

    /**
     * Only attributes with something to show a reader — the baseline or a period
     * carries a filled value. Matches {@see CodexAsOfResolver}'s own filter.
     *
     * @return Collection<int, array{attribute: CodexAttribute, baseline: ?CodexAttributeValue, periods: Collection}>
     */
    public function setOnly(CodexEntry $entry, Event $startEvent): Collection
    {
        return $this->forEntry($entry, $startEvent)
            ->filter(fn (array $sheet) => filled($sheet['baseline']?->value)
                || $sheet['periods']->contains(fn (CodexAttributeValue $period) => filled($period->value)))
            ->values();
    }

    /**
     * The entry's project attributes not yet attached, in picker order.
     *
     * @return Collection<int, CodexAttribute>
     */
    public function unattachedFor(CodexEntry $entry): Collection
    {
        $attachedIds = $this->attached($entry, $entry->project->startEvent())
            ->pluck('attribute.id');

        return $entry->project->codexAttributesFor($entry->type)
            ->reject(fn (CodexAttribute $attribute) => $attachedIds->contains($attribute->id))
            ->values();
    }

    /** @return array{attribute: CodexAttribute, baseline: ?CodexAttributeValue, periods: Collection} */
    private function sheetFor(CodexEntry $entry, CodexAttribute $attribute, Event $startEvent): array
    {
        $periods = (new AttributeTimeline($entry, $attribute))->periods();

        return [
            'attribute' => $attribute,
            'baseline' => $periods->firstWhere('start_event_id', $startEvent->id),
            'periods' => $periods->reject(fn ($period) => $period->start_event_id === $startEvent->id)->values(),
        ];
    }
}
