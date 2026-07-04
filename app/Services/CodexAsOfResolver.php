<?php

namespace App\Services;

use App\Enums\CodexEntryType;
use App\Models\CodexAttribute;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Builds the read-only "as of" view model shown on the scene and event edit pages: every
 * codex entry in the project, with each of its applicable attributes resolved to the value
 * in effect at a given moment, grouped by entry type for display.
 *
 * This is the second app/Services class (after AttributeTimeline): the same resolution +
 * grouping is needed by both SceneController and EventController, so the presentation
 * workflow lives here rather than being duplicated in each controller (guidelines: extract
 * once there is a real second caller). The timeline rule itself stays in AttributeTimeline.
 */
class CodexAsOfResolver
{
    /**
     * Resolve every entry's applicable attributes as of $moment, grouped by type.
     *
     * When $moment is null (an unassigned scene) each value resolves to null so the panel
     * can render the "undetermined" state rather than guessing. Entries and the project's
     * attributes are eager-loaded once (attributeValues.startEvent) so AttributeTimeline
     * resolves each pair from memory instead of querying per pair — no N+1 across entries.
     *
     * @return Collection<int, array{type: CodexEntryType, entries: Collection<int, array{entry: CodexEntry, attributes: Collection<int, array{name: string, value: ?string}>}>}>
     */
    public function resolve(Project $project, ?Event $moment): Collection
    {
        $attributes = $project->codexAttributes()->orderBy('position')->get();

        $entries = $project->codexEntries()
            ->with('attributeValues.startEvent')
            ->orderBy('name')
            ->get();

        return collect(CodexEntryType::cases())
            ->map(fn (CodexEntryType $type) => [
                'type' => $type,
                'entries' => $this->entriesForType($entries, $attributes, $type, $moment),
            ])
            // Drop types with no entries so the panel only lists sections that have content.
            ->filter(fn (array $group) => $group['entries']->isNotEmpty())
            ->values();
    }

    /**
     * Resolve the applicable attributes for every entry of the given type.
     *
     * @param  Collection<int, CodexEntry>  $entries
     * @param  Collection<int, CodexAttribute>  $attributes
     * @return Collection<int, array{entry: CodexEntry, attributes: Collection<int, array{name: string, value: ?string}>}>
     */
    private function entriesForType(Collection $entries, Collection $attributes, CodexEntryType $type, ?Event $moment): Collection
    {
        $applicable = $attributes
            ->filter(fn (CodexAttribute $attribute) => $attribute->appliesTo($type))
            ->values();

        return $entries
            ->filter(fn (CodexEntry $entry) => $entry->type === $type)
            ->map(fn (CodexEntry $entry) => [
                'entry' => $entry,
                'attributes' => $applicable->map(fn (CodexAttribute $attribute) => [
                    'name' => $attribute->name,
                    'value' => $entry->attributeValueAt($attribute, $moment),
                ])->values(),
            ])
            ->values();
    }
}
