<?php

namespace App\Services;

use App\Enums\RevisionOrigin;
use App\Models\Revision;
use App\Support\AutosavableFields;
use App\Support\SaveEntry;
use App\Support\SavePoint;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reads revision history and folds rows into {@see SavePoint} values.
 *
 * Portable grouped queries run in the database. PHP builds the value objects.
 * List queries must not select revision values.
 */
class RevisionHistory
{
    /**
     * Label and manual filters select save points. Only the field filter also
     * limits entries inside a selected save point.
     *
     * @param  array{field?: ?string, label?: ?string, manualOnly?: bool}  $filters
     * @return LengthAwarePaginatorContract<int, SavePoint>
     */
    public function forEntity(Model $entity, array $filters = [], int $page = 1): LengthAwarePaginatorContract
    {
        $perPage = $this->perPage();
        $page = max(1, $page);

        // Load one boundary group for the last "compare with previous" link.
        $groups = $this->groupQuery($entity, $filters)
            ->limit($perPage + 1)
            ->offset(($page - 1) * $perPage)
            ->get();

        return new LengthAwarePaginator(
            $this->foldGroups($entity, $groups, $filters, $perPage),
            $this->countGroups($entity, $filters),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /**
     * @param  array{field?: ?string, label?: ?string, manualOnly?: bool}  $filters
     * @return Collection<int, SavePoint>
     */
    public function savePoints(Model $entity, array $filters = []): Collection
    {
        $groups = $this->groupQuery($entity, $filters)->get();

        return $this->foldGroups($entity, $groups, $filters, $groups->count());
    }

    /**
     * @param  array{field?: ?string, label?: ?string, manualOnly?: bool}  $filters
     * @return Builder<Revision>
     */
    private function groupQuery(Model $entity, array $filters): Builder
    {
        return $this->rowQuery($entity, $filters)
            ->select('save_id')
            ->selectRaw('MAX(created_at) as saved_at')
            ->selectRaw('MAX(id) as last_id')
            ->groupBy('save_id')
            ->orderByDesc('saved_at')
            ->orderByDesc('last_id');
    }

    /** @param array{field?: ?string, label?: ?string, manualOnly?: bool} $filters */
    private function countGroups(Model $entity, array $filters): int
    {
        return $this->rowQuery($entity, $filters)->distinct()->count('save_id');
    }

    /**
     * Removes the relation's default order before callers add grouping.
     * PostgreSQL and strict MySQL reject that order with GROUP BY.
     *
     * @param  array{field?: ?string, label?: ?string, manualOnly?: bool}  $filters
     * @return Builder<Revision>
     */
    private function rowQuery(Model $entity, array $filters): Builder
    {
        $field = $filters['field'] ?? null;
        $label = trim((string) ($filters['label'] ?? ''));

        return $entity->revisions()
            ->getQuery()
            ->reorder()
            ->when($field !== null && $field !== '', fn (Builder $query) => $query->where('field', $field))
            ->when($label !== '', fn (Builder $query) => $query->where('label', 'like', '%'.$label.'%'))
            ->when($filters['manualOnly'] ?? false, fn (Builder $query) => $query->where('origin', RevisionOrigin::Manual));
    }

    /**
     * @param  Collection<int, Revision>  $groups
     * @param  array{field?: ?string, label?: ?string, manualOnly?: bool}  $filters
     * @return Collection<int, SavePoint>
     */
    private function foldGroups(Model $entity, Collection $groups, array $filters, int $limit): Collection
    {
        $rendered = $groups->take($limit)->values();

        if ($rendered->isEmpty()) {
            return collect();
        }

        $rowsBySave = $this->rowsFor($entity, $rendered->pluck('save_id')->all(), $filters);
        $currentSaveId = $this->currentSaveId($entity);

        return $rendered->map(function (Revision $group, int $index) use ($entity, $groups, $rowsBySave, $currentSaveId): SavePoint {
            /** @var Collection<int, Revision> $rows */
            $rows = $rowsBySave->get($group->save_id, collect());

            return new SavePoint(
                saveId: $group->save_id,
                // Aggregate aliases do not pass through model casts.
                savedAt: Carbon::parse($group->saved_at),
                authorName: $rows->first()?->user?->name,
                label: $rows->pluck('label')->filter()->first(),
                origin: SavePoint::dominantOrigin($rows->pluck('origin')),
                isCurrent: $group->save_id === $currentSaveId,
                lastRevisionId: (int) $group->last_id,
                previousSaveId: $groups->get($index + 1)?->save_id,
                entries: $this->entriesFor($entity, $rows),
            );
        });
    }

    /**
     * > [!IMPORTANT]
     * > Do not select `value`. History lists use size and summary fields instead.
     *
     * @param  list<string>  $saveIds
     * @param  array{field?: ?string, label?: ?string, manualOnly?: bool}  $filters
     * @return Collection<string, Collection<int, Revision>>
     */
    private function rowsFor(Model $entity, array $saveIds, array $filters): Collection
    {
        return $entity->revisions()
            ->getQuery()
            ->reorder()
            ->whereIn('save_id', $saveIds)
            ->when(
                ($filters['field'] ?? null) !== null && $filters['field'] !== '',
                fn (Builder $query) => $query->where('field', $filters['field']),
            )
            ->select(['id', 'save_id', 'field', 'created_at', 'user_id', 'label', 'origin', 'size_bytes', 'summary_html', 'change_count'])
            ->with('user:id,name')
            ->get()
            ->groupBy('save_id');
    }

    /**
     * @param  Collection<int, Revision>  $rows
     * @return Collection<int, SaveEntry> Entries in registry field order.
     */
    private function entriesFor(Model $entity, Collection $rows): Collection
    {
        $kinds = AutosavableFields::fieldsForModel($entity::class);
        $byField = $rows->keyBy('field');

        return collect($kinds)
            ->map(function ($kind, string $field) use ($byField): ?SaveEntry {
                $row = $byField->get($field);

                if ($row === null) {
                    return null;
                }

                return new SaveEntry(
                    revisionId: $row->id,
                    field: $field,
                    kind: $kind,
                    summaryHtml: $row->summary_html,
                    changeCount: (int) $row->change_count,
                    sizeBytes: (int) $row->size_bytes,
                );
            })
            ->filter()
            ->values();
    }

    /** Returns the unfiltered newest save point that represents current state. */
    private function currentSaveId(Model $entity): ?string
    {
        return $this->groupQuery($entity, [])->first()?->save_id;
    }

    private function perPage(): int
    {
        return max(1, (int) config('revisions.history.per_page'));
    }
}
