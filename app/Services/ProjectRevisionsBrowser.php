<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Revision;
use App\Support\AutosavableFields;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds the sidebar tree for the project-scoped revisions browser
 * (RevisionBrowserController): every entity in a project that actually has
 * revision history, and under each the fields that have it, with a per-field
 * revision count and a link to that field's history page.
 *
 * Follows the ProjectSearch template (CLAUDE.md's "reusable domain workflows"):
 * the controller resolves + authorizes the Project, this service owns the
 * queries. It is the one place the browser's tree is assembled.
 *
 * Only entities/fields with at least one revision appear — the tree is driven
 * entirely from the grouped `revisions` rows, so a never-edited entity never
 * enters it (the sidebar-scope decision confirmed with the user).
 *
 * Never hydrates `revisions.value`: the browser only ever needs counts and the
 * (type, id, field) coordinates, so it selects nothing else — the same
 * "list queries never hydrate value" invariant RevisionController::index keeps.
 */
class ProjectRevisionsBrowser
{
    /**
     * The entity-type groups the sidebar renders, in display order, as
     * `slug => heading`. Also the iteration order of the returned tree.
     *
     * Keyed by the same URL slugs as App\Support\AutosavableFields (the
     * browser's per-field links resolve through the `revisions.index` route,
     * which gates on those slugs) — a type with no registered slug can never
     * have a revision, so this list and the registry stay in lock-step.
     *
     * @var array<string, string>
     */
    private const GROUPS = [
        'project' => 'Project',
        'act' => 'Acts',
        'chapter' => 'Chapters',
        'scene' => 'Scenes',
        'plotline' => 'Plotlines',
        'event' => 'Events',
        'codex' => 'Codex',
    ];

    /**
     * Assemble the tree for one already-authorized project.
     *
     * One grouped query over `revisions` (scoped by the real `project_id` FK
     * every revision carries — see App\Services\RevisionRecorder, which always
     * stamps it) yields the (type, id, field) triples that have history, plus
     * their counts. A second, tiny query per present entity type then loads
     * just the id + display name of the referenced entities. No other query
     * runs, and `value` is never selected.
     *
     * @return Collection<int, object{
     *     type: string,
     *     label: string,
     *     entities: Collection<int, object{
     *         id: int,
     *         name: string,
     *         fields: Collection<int, object{field: string, label: string, count: int, url: string, entity: string}>
     *     }>
     * }>
     */
    public function tree(Project $project): Collection
    {
        // (revisionable_type, revisionable_id, field) => count, for this project,
        // grouped by the polymorphic type so each group is resolved independently
        // below. `revisionable_type` holds the FQCN (no morph map is registered).
        $countsByType = Revision::query()
            ->where('project_id', $project->id)
            ->groupBy('revisionable_type', 'revisionable_id', 'field')
            ->select('revisionable_type', 'revisionable_id', 'field')
            ->selectRaw('COUNT(*) as revision_count')
            ->get()
            ->groupBy('revisionable_type');

        return collect(self::GROUPS)
            ->map(function (string $label, string $slug) use ($countsByType) {
                $modelClass = AutosavableFields::modelFor($slug);
                $rows = $countsByType->get($modelClass);

                if ($rows === null || $rows->isEmpty()) {
                    return null;
                }

                return (object) [
                    'type' => $slug,
                    'label' => $label,
                    'entities' => $this->entitiesFor($slug, $modelClass, $rows),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Turn one entity type's grouped revision rows into the sidebar's
     * entity → fields sub-tree, ordered by entity display name.
     *
     * @param  class-string  $modelClass
     * @param  Collection<int, object>  $rows  grouped revision rows for this type
     * @return Collection<int, object{id: int, name: string, fields: Collection}>
     */
    private function entitiesFor(string $slug, string $modelClass, Collection $rows): Collection
    {
        // The single column that titles this entity type — `name` for all but
        // Event (`title`), owned by HasRevisions::revisionDisplayColumn() so the
        // exception lives in exactly one place (RevisionController reads the same
        // source through revisionDisplayName()).
        $displayColumn = $modelClass::revisionDisplayColumn();

        // Just the id + the one column the sidebar prints — never the entity's
        // own rich/large text columns.
        $names = $modelClass::query()
            ->whereIn('id', $rows->pluck('revisionable_id')->unique())
            ->get(['id', $displayColumn])
            ->keyBy('id');

        return $rows
            ->groupBy('revisionable_id')
            ->map(function (Collection $fieldRows, int $id) use ($slug, $names, $displayColumn) {
                return (object) [
                    'id' => $id,
                    'name' => (string) ($names->get($id)?->getAttribute($displayColumn) ?? '#'.$id),
                    'fields' => $this->fieldsFor($slug, $id, $fieldRows),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * The field leaves for one entity, ordered by the registry's field order
     * (so e.g. a Scene lists Description before Notes before Contents), keeping
     * only fields that actually have a revision row.
     *
     * @param  Collection<int, object>  $fieldRows  grouped revision rows for one entity
     * @return Collection<int, object{field: string, label: string, count: int, url: string, entity: string}>
     */
    private function fieldsFor(string $slug, int $id, Collection $fieldRows): Collection
    {
        $countByField = $fieldRows->keyBy('field');

        return collect(array_keys(AutosavableFields::REGISTRY[$slug][1]))
            ->filter(fn (string $field) => $countByField->has($field))
            ->map(fn (string $field) => (object) [
                'field' => $field,
                'label' => Str::headline($field),
                'count' => (int) $countByField->get($field)->revision_count,
                'url' => route('revisions.index', ['entity' => $slug, 'id' => $id, 'field' => $field]),
                'entity' => $slug,
            ])
            ->values();
    }
}
