<?php

namespace App\Services;

use App\Enums\RevisionOrigin;
use App\Models\Revision;
use App\Support\RevisionPurgeResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * The one place the application's *explicit, destructive* revision-deletion
 * rules live (handoff.md §4.3).
 *
 * This is the opposite number of Revision::prunable() (task 1): prune is the
 * unattended, safety-preserving daily sweep that never touches a labeled or
 * non-`automatic` row; purge is a deliberate action the user asked for, and
 * is explicitly *allowed* to remove those rows — without it, imported
 * revisions and a two-year `manual` history would be a one-way ratchet with
 * no release valve.
 *
 * Both entry points call this single service, so the rules can never drift
 * between them:
 *   - App\Console\Commands\PurgeRevisions (`revisions:purge`)
 *   - The "Revision storage" admin panel's controller (task 13)
 */
class RevisionPurger
{
    /**
     * A category is a cross-cutting slice of the `revisions` table, not the
     * same thing as a RevisionOrigin case: "labeled" matches on the `label`
     * column regardless of origin (a manual or automatic revision can both
     * be labeled), while the other three categories match on origin.
     */
    public const CATEGORY_AUTOMATIC = 'automatic';

    public const CATEGORY_MANUAL = 'manual';

    public const CATEGORY_LABELED = 'labeled';

    public const CATEGORY_IMPORTED = 'imported';

    /**
     * @var list<string>
     */
    public const CATEGORIES = [
        self::CATEGORY_AUTOMATIC,
        self::CATEGORY_MANUAL,
        self::CATEGORY_LABELED,
        self::CATEGORY_IMPORTED,
    ];

    /**
     * Delete (or, in dry-run mode, merely count) the revisions matching one
     * category, optionally narrowed to a single project and/or an age
     * cutoff.
     *
     * Dry-run and a real run compute the exact same query (`queryFor()`), so
     * calling this with `dryRun: true` immediately followed by `dryRun:
     * false` is guaranteed to report matching counts — there is no separate
     * "estimate" code path to drift from the real one.
     */
    public function purge(
        string $category,
        ?int $projectId = null,
        ?Carbon $before = null,
        bool $dryRun = false,
    ): RevisionPurgeResult {
        $query = $this->queryFor($category, $projectId, $before);

        // Snapshot the count/size before any delete() call below empties
        // the query's result set out from under it.
        $count = (clone $query)->count();
        $sizeBytes = (int) (clone $query)->sum('size_bytes');

        if (! $dryRun) {
            $query->delete();
        }

        return new RevisionPurgeResult($count, $sizeBytes);
    }

    /**
     * Build the query for one purge category. Selects/aggregates scalar
     * columns only (`count()`/`sum('size_bytes')` in purge(), a bulk
     * `delete()` that never hydrates a model) — never hydrates `value`.
     */
    private function queryFor(string $category, ?int $projectId, ?Carbon $before): Builder
    {
        if (! in_array($category, self::CATEGORIES, true)) {
            throw new InvalidArgumentException("Unknown revision purge category [{$category}].");
        }

        $query = Revision::query();

        match ($category) {
            self::CATEGORY_AUTOMATIC => $query->where('origin', RevisionOrigin::Automatic),
            self::CATEGORY_MANUAL => $query->where('origin', RevisionOrigin::Manual),
            self::CATEGORY_LABELED => $query->whereNotNull('label'),
            self::CATEGORY_IMPORTED => $query->where('origin', RevisionOrigin::Import),
        };

        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }

        if ($before !== null) {
            $query->where('created_at', '<', $before);
        }

        return $query;
    }
}
