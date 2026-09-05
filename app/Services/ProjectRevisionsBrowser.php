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
 * Builds the project revision sidebar from grouped revision counts.
 *
 * It omits entities and fields without history. Story entities group by book.
 * Project-wide entities do not. List queries never select revision values.
 */
class ProjectRevisionsBrowser
{
    /** @var array<string, string> Entity headings in display order. */
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

    /** @var list<string> Entity slugs that group under books. */
    private const MANUSCRIPT_GROUPS = ['act', 'chapter', 'scene'];

    /**
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
        // Add a composite index only if measured browser performance requires it.
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

    /** Groups story entities by book and other entities in one unnamed bucket. */
    private function booksFor(string $slug, string $modelClass, Collection $rows, Project $project): Collection
    {
        $entities = $this->entitiesFor($slug, $modelClass, $rows, $project);

        if (! in_array($slug, self::MANUSCRIPT_GROUPS, true)) {
            return collect([(object) ['id' => null, 'name' => null, 'entities' => $entities]]);
        }

        $bookIdByEntity = $this->bookIdsFor($slug, $entities->pluck('id'));

        // Set the known project so unnamed books do not query it again.
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
     * @param  Collection<int, int>  $ids
     * @return Collection<int, int> Revisionable ID to book ID.
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
     * @param  class-string  $modelClass
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object{id: int, name: string, url: string, fields: Collection}>
     */
    private function entitiesFor(string $slug, string $modelClass, Collection $rows, Project $project): Collection
    {
        $displayColumn = $modelClass::revisionDisplayColumn();

        // Select only the ID and display column.
        $names = $modelClass::query()
            ->whereIn('id', $rows->pluck('revisionable_id')->unique())
            ->get(['id', $displayColumn])
            // Set the known project so unnamed books do not query it again.
            ->each(fn ($entity) => $entity instanceof Book ? $entity->setRelation('project', $project) : null)
            ->keyBy('id');

        return $rows
            ->groupBy('revisionable_id')
            ->map(function (Collection $fieldRows, int $id) use ($slug, $names) {
                return (object) [
                    'id' => $id,
                    'name' => $names->get($id)?->revisionDisplayName() ?? '#'.$id,
                    'url' => route('revisions.index', ['entity' => $slug, 'id' => $id]),
                    'fields' => $this->fieldsFor($slug, $id, $fieldRows),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * @param  Collection<int, object>  $fieldRows
     * @return Collection<int, object{field: string, label: string, count: int, url: string, entity: string}>
     */
    private function fieldsFor(string $slug, int $id, Collection $fieldRows): Collection
    {
        $countByField = $fieldRows->keyBy('field');

        return collect(array_keys(AutosavableFields::fieldsFor($slug)))
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
