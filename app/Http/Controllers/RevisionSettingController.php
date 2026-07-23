<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRevisionSettingRequest;
use App\Models\Revision;
use App\Models\RevisionSetting;
use App\Services\RevisionPurger;
use App\Support\RevisionPurgeResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The admin "Revisions" page (task 13): the RevisionSetting retention form
 * (confirm-gated when lowering the window) and the "Revision storage" panel's
 * per-category counts + bulk-delete actions.
 *
 * This is the SECOND of RevisionPurger's two call sites — the other is the
 * `revisions:purge` artisan command (App\Console\Commands\PurgeRevisions).
 * Both go through the same service so the deletion rules can never drift
 * between the CLI and this page. This controller owns no purge rules itself.
 */
class RevisionSettingController extends Controller
{
    /**
     * How far back "auto older than 1 year" reaches for the storage panel's
     * age-based bulk-delete action (handoff.md §4.3's worked example). A
     * named constant rather than a magic 365 sprinkled through the
     * controller/view, per CLAUDE.md's "avoid magic numbers" rule.
     */
    private const OLD_AUTOMATIC_THRESHOLD_DAYS = 365;

    public function edit(RevisionPurger $purger): View
    {
        return view('admin.revisions.edit', [
            'retentionDays' => RevisionSetting::current()->retention_days,
            'storage' => $this->storageBreakdown($purger),
        ]);
    }

    /**
     * Persist a new retention_days value.
     *
     * Raising the value is safe (it can never delete anything) and saves
     * immediately. Lowering it is confirm-gated: the first submission
     * returns a confirmation screen showing exactly how many revisions the
     * next nightly prune would remove under the new value — computed from
     * the REAL Revision::prunable() query object (see countPrunableAt()
     * below), never a hand-rolled estimate (handoff.md §9.11). Nothing is
     * persisted until the confirming submission (`confirmed=1`) arrives.
     */
    public function update(UpdateRevisionSettingRequest $request): View|RedirectResponse
    {
        $setting = RevisionSetting::current();
        $newRetentionDays = $request->integer('retention_days');
        $isLowering = $newRetentionDays < $setting->retention_days;

        if ($isLowering && ! $request->boolean('confirmed')) {
            return view('admin.revisions.confirm-retention', [
                'currentRetentionDays' => $setting->retention_days,
                'newRetentionDays' => $newRetentionDays,
                'prunableCount' => $this->countPrunableAt($newRetentionDays),
            ]);
        }

        $setting->update(['retention_days' => $newRetentionDays]);

        return redirect()
            ->route('admin.revisions.edit')
            ->with('status', 'revision-settings-updated');
    }

    /**
     * Delete every revision in one whole RevisionPurger category
     * (automatic/manual/labeled/imported) — the "per category" bulk-delete
     * actions on the storage panel. `{category}` is constrained at the
     * router to RevisionPurger::CATEGORIES, so an unknown value never
     * reaches here.
     */
    public function purgeCategory(string $category, RevisionPurger $purger): RedirectResponse
    {
        $result = $purger->purge($category);

        return redirect()
            ->route('admin.revisions.edit')
            ->with('status', 'revisions-purged')
            ->with('purgedCount', $result->count);
    }

    /**
     * Delete automatic revisions older than one year — the "per age"
     * bulk-delete example from handoff.md §4.3 ("auto older than 1 year").
     * Unlike prune, this is explicitly allowed to remove the last remaining
     * automatic revision for a field, since the user asked for it directly.
     */
    public function purgeOldAutomatic(RevisionPurger $purger): RedirectResponse
    {
        $result = $purger->purge(
            RevisionPurger::CATEGORY_AUTOMATIC,
            before: now()->subDays(self::OLD_AUTOMATIC_THRESHOLD_DAYS),
        );

        return redirect()
            ->route('admin.revisions.edit')
            ->with('status', 'revisions-purged')
            ->with('purgedCount', $result->count);
    }

    /**
     * Per-category counts + SUM(size_bytes), for the storage panel. Reuses
     * RevisionPurger's own dry-run query (rather than a second, hand-rolled
     * query) so the panel's figures can never drift from what a bulk-delete
     * button actually removes — and, per 00-overview.md's read rule, never
     * hydrates `value`.
     *
     * @return array<string, RevisionPurgeResult>
     */
    private function storageBreakdown(RevisionPurger $purger): array
    {
        $breakdown = [];

        foreach (RevisionPurger::CATEGORIES as $category) {
            $breakdown[$category] = $purger->purge($category, dryRun: true);
        }

        return $breakdown;
    }

    /**
     * Count how many revisions Revision::prunable() would remove if
     * `retention_days` were already set to $retentionDays — WITHOUT actually
     * persisting that value.
     *
     * Rather than duplicate prunable()'s "keep the newest row per field,
     * never touch a labeled or non-automatic row" logic here (which would
     * risk the confirm-count silently drifting from what the real nightly
     * prune does), this applies the candidate value to the singleton inside
     * a transaction, runs the real prunable() query, then always rolls back
     * — so the change never leaves the transaction, even though prunable()
     * reads it back from the database via RevisionSetting::current() (which
     * is deliberately not memoised, see that class's docblock).
     */
    private function countPrunableAt(int $retentionDays): int
    {
        DB::beginTransaction();

        try {
            RevisionSetting::current()->update(['retention_days' => $retentionDays]);

            return (new Revision)->prunable()->count();
        } finally {
            DB::rollBack();
        }
    }
}
