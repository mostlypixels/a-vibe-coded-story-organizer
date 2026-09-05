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
    public function forEntry(CodexEntry $entry, Event $startEvent): Collection
    {
        return $entry->project->codexAttributesFor($entry->type)
            ->map(fn (CodexAttribute $attribute) => $this->sheetFor($entry, $attribute, $startEvent));
    }

    /**
     * Only attributes with a baseline or at least one period — the read page
     * has nothing to show for an attribute the entry has never been given.
     *
     * @return Collection<int, array{attribute: CodexAttribute, baseline: ?CodexAttributeValue, periods: Collection}>
     */
    public function setOnly(CodexEntry $entry, Event $startEvent): Collection
    {
        return $this->forEntry($entry, $startEvent)
            ->reject(fn (array $sheet) => $sheet['baseline'] === null && $sheet['periods']->isEmpty())
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
