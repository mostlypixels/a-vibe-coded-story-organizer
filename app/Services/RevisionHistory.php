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
 * Every read query the history page and the compare pickers make.
 *
 * Follows the {@see ProjectSearch} template (CLAUDE.md's "reusable domain
 * workflows"): the controller resolves and authorizes the entity, this service
 * owns the queries. It is the one place `revisions` rows are folded into
 * {@see SavePoint}s.
 *
 * ## Two queries, and the folding happens in PHP
 *
 * 1. a `GROUP BY save_id` over the entity's rows, for the page of save points;
 * 2. the rows belonging to those save points — **never selecting `value`**.
 *
 * No window functions, no `GROUP_CONCAT`, no engine-specific string
 * concatenation: this app runs on five database engines, and the only way to be
 * sure they all produce the same plan is to ask each of them for very little.
 * Turning rows into value objects is PHP's job, over at most one page of rows.
 *
 * > [!NOTE]
 * > There is no "revisions without a `save_id`" branch anywhere in here, and
 * > there should never be one. The migration that introduced save points
 * > deleted the rows that predated it precisely so this code has one case
 * > instead of two (see `documentation/architecture.md` → *Revisions*).
 *
 * > [!NOTE]
 * > {@see self::savePoints()} deliberately does **not** paginate: it feeds the
 * > compare pickers, which are a bounded list narrowed by their own in-panel
 * > filters rather than by paging. It loads one entity's rows without their
 * > values, so the cost is small — but if an entity ever accumulates enough
 * > history to make that untrue, this method is the seam to change.
 */
class RevisionHistory
{
    /**
     * One page of an entity's history, newest save point first.
     *
     * `$filters` accepts `field` (exact), `label` (substring, matching the
     * search box that predates this page) and `manualOnly` (deliberate
     * checkpoints only). They narrow which save *points* appear; only `field`
     * also narrows the entries inside them, because a save point that qualifies
     * should show what it actually contains.
     *
     * @param  array{field?: ?string, label?: ?string, manualOnly?: bool}  $filters
     * @return LengthAwarePaginatorContract<int, SavePoint>
     */
    public function forEntity(Model $entity, array $filters = [], int $page = 1): LengthAwarePaginatorContract
    {
        $perPage = $this->perPage();
        $page = max(1, $page);

        // One group beyond the page. It is never rendered — it exists so the
        // last row on the page can still name the save point before it, which
        // is what its "compare with previous" link addresses.
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
     * Every save point of an entity, newest first — the option list the compare
     * pickers render.
     *
     * @param  array{field?: ?string, label?: ?string, manualOnly?: bool}  $filters
     * @return Collection<int, SavePoint>
     */
    public function savePoints(Model $entity, array $filters = []): Collection
    {
        $groups = $this->groupQuery($entity, $filters)->get();

        return $this->foldGroups($entity, $groups, $filters, $groups->count());
    }

    /**
     * The save points, as one row each: the id, when the save finished, and the
     * id of its last row (the tie-break — a burst can write several rows inside
     * the same second, and `created_at` alone would order them arbitrarily).
     *
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

    /**
     * How many save points the filters match, for the paginator's total.
     *
     * @param  array{field?: ?string, label?: ?string, manualOnly?: bool}  $filters
     */
    private function countGroups(Model $entity, array $filters): int
    {
        return $this->rowQuery($entity, $filters)->distinct()->count('save_id');
    }

    /**
     * The entity's revision rows, filtered but neither grouped nor ordered.
     *
     * Two details are load-bearing. `getQuery()` descends from the relation to
     * the query builder it wraps, so the morph constraints are still applied but
     * `select()`/`groupBy()` return something this method can type. And
     * `reorder()` drops the newest-first sort `HasRevisions::revisions()` adds by
     * default: an `ORDER BY created_at` left hanging on a `GROUP BY save_id`
     * query is a hard error on PostgreSQL and under MySQL's `ONLY_FULL_GROUP_BY`.
     * Callers that want an order state it themselves.
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
            // Substring, not exact: this is the label *search* box the
            // per-field history page already had, kept as it was.
            ->when($label !== '', fn (Builder $query) => $query->where('label', 'like', '%'.$label.'%'))
            ->when($filters['manualOnly'] ?? false, fn (Builder $query) => $query->where('origin', RevisionOrigin::Manual));
    }

    /**
     * Fold grouped rows into `SavePoint`s, rendering only the first `$limit` of
     * them.
     *
     * `$groups` may hold one more than `$limit` — the boundary group — which is
     * read for its id and then dropped.
     *
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
                // Parsed by hand: `saved_at` is an aggregate alias, so it
                // arrives as a raw driver string rather than through the
                // model's `created_at` cast.
                savedAt: Carbon::parse($group->saved_at),
                authorName: $rows->first()?->user?->name,
                // The first label any row carries: a save labels the *event*,
                // and every row written by one Save is stamped with the same
                // one, so "first non-null" is a tie-break that rarely fires.
                label: $rows->pluck('label')->filter()->first(),
                origin: SavePoint::dominantOrigin($rows->pluck('origin')),
                isCurrent: $group->save_id === $currentSaveId,
                // Newest-first ordering means "the one after this in the list".
                previousSaveId: $groups->get($index + 1)?->save_id,
                entries: $this->entriesFor($entity, $rows),
            );
        });
    }

    /**
     * The rows of the given save points, keyed by `save_id`.
     *
     * > [!IMPORTANT]
     * > `value` is not in the select list, and must not be added to it. A page
     * > of history can span dozens of revisions of a scene's contents; loading
     * > them would mean megabytes read to render text nobody asked to see.
     * > `size_bytes` and `summary_html` exist precisely so this query stays small.
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
            // Only the field filter narrows the entries: a save point that
            // survived a label or manual-only filter still describes everything
            // that save actually touched.
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
     * One save point's rows as `SaveEntry`s, in registry field order.
     *
     * Registry order rather than write order, so a save that touched a
     * project's description and its dedication lists them the same way every
     * time — and the same way the edit form does.
     *
     * @param  Collection<int, Revision>  $rows
     * @return Collection<int, SaveEntry>
     */
    private function entriesFor(Model $entity, Collection $rows): Collection
    {
        $kinds = AutosavableFields::REGISTRY[AutosavableFields::slugFor($entity::class)][1];
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

    /**
     * The entity's newest save point — the one its live state came from.
     *
     * Resolved with **no filters applied**, deliberately: "Current" is a fact
     * about the entity, not about the list being looked at. Deriving it from
     * the filtered page would crown whatever happened to be at the top of it and
     * tell the writer that an old save is her current text.
     */
    private function currentSaveId(Model $entity): ?string
    {
        return $this->groupQuery($entity, [])->first()?->save_id;
    }

    private function perPage(): int
    {
        return max(1, (int) config('revisions.history.per_page'));
    }
}
