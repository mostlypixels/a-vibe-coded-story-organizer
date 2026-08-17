<?php

namespace App\Services;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
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
 *
 * The browser stays project-scoped — `revisions.project_id` never changes —
 * but a project can hold several books, so each act, chapter and scene is
 * additionally grouped under its own book (MANUSCRIPT_GROUPS). The project,
 * the books themselves, plotlines, events and codex entries are project-wide,
 * not per book, so they render ungrouped. See booksFor().
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
        'book' => 'Books',
        'act' => 'Acts',
        'chapter' => 'Chapters',
        'scene' => 'Scenes',
        'plotline' => 'Plotlines',
        'event' => 'Events',
        'codex' => 'Codex',
    ];

    /**
     * The GROUPS slugs that are book-scoped. The sidebar groups these under
     * a book heading; every other slug is project-scoped and renders as one
     * ungrouped list, exactly as before.
     *
     * @var list<string>
     */
    private const MANUSCRIPT_GROUPS = ['act', 'chapter', 'scene'];

    /**
     * Assemble the tree for one already-authorized project.
     *
     * One grouped query over `revisions` (scoped by the real `project_id` FK
     * every revision carries — see App\Services\RevisionRecorder, which always
     * stamps it) yields the (type, id, field) triples that have history, plus
     * their counts. A second, tiny query per present entity type then loads
     * just the id + display name of the referenced entities, and — for the
     * manuscript types only — a third tiny query resolves each entity's book.
     * No other query runs, and `value` is never selected.
     *
     * Acts, chapters and scenes are grouped under the book they belong to
     * (MANUSCRIPT_GROUPS); the project, the books, plotlines, events and codex
     * entries are project-scoped, so each comes back as one ungrouped bucket
     * (`books` holding a single entry with `name: null`) — see booksFor().
     *
     * @return Collection<int, object{
     *     type: string,
     *     label: string,
     *     books: Collection<int, object{
     *         id: ?int,
     *         name: ?string,
     *         entities: Collection<int, object{
     *             id: int,
     *             name: string,
     *             url: string,
     *             fields: Collection<int, object{field: string, label: string, count: int, url: string, entity: string}>
     *         }>
     *     }>
     * }>
     */
    public function tree(Project $project): Collection
    {
        // (revisionable_type, revisionable_id, field) => count, for this project,
        // grouped by the polymorphic type so each group is resolved independently
        // below. `revisionable_type` holds the FQCN (no morph map is registered).
        //
        // Only `project_id` is indexed, so the grouping itself is unindexed work.
        // Left that way deliberately: this runs once per visit to the browser
        // landing page, while `revisions` is the most written-to table in the app
        // (every autosave), and an index earns its write cost only against a
        // measured read. If this page ever feels slow, the seam to widen is a
        // composite (project_id, revisionable_type, revisionable_id, field).
        $countsByType = Revision::query()
            ->where('project_id', $project->id)
            ->groupBy('revisionable_type', 'revisionable_id', 'field')
            ->select('revisionable_type', 'revisionable_id', 'field')
            ->selectRaw('COUNT(*) as revision_count')
            ->get()
            ->groupBy('revisionable_type');

        return collect(self::GROUPS)
            ->map(function (string $label, string $slug) use ($countsByType, $project) {
                $modelClass = AutosavableFields::modelFor($slug);
                $rows = $countsByType->get($modelClass);

                if ($rows === null || $rows->isEmpty()) {
                    return null;
                }

                return (object) [
                    'type' => $slug,
                    'label' => $label,
                    'books' => $this->booksFor($slug, $modelClass, $rows, $project),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Group one entity type's revised entities by book, in book position
     * order. Only MANUSCRIPT_GROUPS actually belong to a book — every other
     * type comes back as a single bucket (`id: null, name: null`) holding
     * every entity, so the sidebar renders no heading for it and still walks
     * one shape either way.
     */
    private function booksFor(string $slug, string $modelClass, Collection $rows, Project $project): Collection
    {
        $entities = $this->entitiesFor($slug, $modelClass, $rows, $project);

        if (! in_array($slug, self::MANUSCRIPT_GROUPS, true)) {
            return collect([(object) ['id' => null, 'name' => null, 'entities' => $entities]]);
        }

        $bookIdByEntity = $this->bookIdsFor($slug, $entities->pluck('id'));

        // The project is already known — every book here belongs to it — so
        // the inverse relation is set by hand instead of an eager-loaded
        // `project` column. displayName() falls back to it for an unnamed
        // book; without this it would re-query the project once per unnamed
        // book (the same N+1 App\Models\Book's docblock warns about).
        $booksById = Book::query()
            ->whereIn('id', $bookIdByEntity->unique()->values())
            ->get(['id', 'name', 'position'])
            ->each(fn (Book $book) => $book->setRelation('project', $project))
            ->keyBy('id');

        return $entities
            ->groupBy(fn (object $entity) => $bookIdByEntity->get($entity->id))
            ->map(fn (Collection $bookEntities, int $bookId) => (object) [
                'id' => $bookId,
                'name' => $booksById->get($bookId)?->displayName() ?? '#'.$bookId,
                'entities' => $bookEntities->values(),
            ])
            ->sortBy(fn (object $bookGroup) => $booksById->get($bookGroup->id)?->position)
            ->values();
    }

    /**
     * The book each revised entity belongs to, as revisionable_id => book_id.
     * Only Act carries `book_id` directly; a chapter reaches it through its
     * act, a scene through its chapter's act — so each needs its own join.
     *
     * @param  Collection<int, int>  $ids
     * @return Collection<int, int>
     */
    private function bookIdsFor(string $slug, Collection $ids): Collection
    {
        return match ($slug) {
            'act' => Act::query()->whereIn('id', $ids)->pluck('book_id', 'id'),
            'chapter' => Chapter::query()
                ->join('acts', 'acts.id', '=', 'chapters.act_id')
                ->whereIn('chapters.id', $ids)
                ->pluck('acts.book_id', 'chapters.id'),
            'scene' => Scene::query()
                ->join('chapters', 'chapters.id', '=', 'scenes.chapter_id')
                ->join('acts', 'acts.id', '=', 'chapters.act_id')
                ->whereIn('scenes.id', $ids)
                ->pluck('acts.book_id', 'scenes.id'),
            default => collect(),
        };
    }

    /**
     * Turn one entity type's grouped revision rows into the sidebar's
     * entity → fields sub-tree, ordered by entity display name.
     *
     * @param  class-string  $modelClass
     * @param  Collection<int, object>  $rows  grouped revision rows for this type
     * @return Collection<int, object{id: int, name: string, url: string, fields: Collection}>
     */
    private function entitiesFor(string $slug, string $modelClass, Collection $rows, Project $project): Collection
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
            // A Book prints its project's name while it has none of its own, so
            // the inverse relation is set by hand. Same N+1 booksFor() avoids.
            ->each(fn ($entity) => $entity instanceof Book ? $entity->setRelation('project', $project) : null)
            ->keyBy('id');

        return $rows
            ->groupBy('revisionable_id')
            ->map(function (Collection $fieldRows, int $id) use ($slug, $names) {
                return (object) [
                    'id' => $id,
                    'name' => $names->get($id)?->revisionDisplayName() ?? '#'.$id,
                    // The entity's *unfiltered* history — the whole-entity view
                    // its field leaves are a `?field=` filter on top of. Built
                    // here rather than in Blade for the same reason the leaves'
                    // URLs are (see fieldsFor()): the tree owns its own links.
                    'url' => route('revisions.index', ['entity' => $slug, 'id' => $id]),
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
