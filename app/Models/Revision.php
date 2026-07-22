<?php

namespace App\Models;

use App\Enums\RevisionOrigin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One immutable snapshot of a single revisionable field, at a single point in
 * time.
 *
 * Rows are never updated by the application after their coalescing window
 * closes (§2.2 of the autosave-with-revisions spec) — a still-open row is
 * overwritten via a plain UPDATE in App\Services\RevisionRecorder (task 4),
 * not Eloquent's dirty-tracking `save()`. `$timestamps` is disabled because
 * there is no `updated_at` column at all; `created_at` is always set
 * explicitly by the writer, never left to the database default.
 *
 * `project_id` is a real foreign key (not inferred from the polymorphic
 * `revisionable_type`/`revisionable_id` pair) because deleting a Project
 * cascades to its acts/chapters/scenes at the DB level without firing
 * Eloquent events — a `deleting` hook here would silently never run. See
 * documentation/architecture.md → "Revisions" once task 16 writes it.
 */
class Revision extends Model
{
    use HasFactory;
    use MassPrunable;

    public $timestamps = false;

    protected $fillable = [
        'revisionable_type',
        'revisionable_id',
        'field',
        'value',
        'size_bytes',
        'project_id',
        'user_id',
        'label',
        'origin',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'origin' => RevisionOrigin::class,
            'created_at' => 'datetime',
        ];
    }

    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The user who wrote this revision (the project owner, for a `baseline` row —
     * see App\Services\RevisionRecorder::ensureBaseline()). The history page
     * (task 10) eager-loads this selecting only `id`/`name`, never pulling in
     * anything from `revisions.value`.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The query {@see MassPrunable} runs (via the scheduled `model:prune`
     * command) to delete eligible rows in bulk.
     *
     * This is the single most safety-critical query in the whole feature
     * (handoff.md §4.2): it must delete an "automatic", unlabeled row once it
     * is older than the retention window, *unless* that row is the newest
     * revision recorded for its (revisionable_type, revisionable_id, field)
     * triple — losing the only remaining history for a field would be a real
     * data loss, not just tidying.
     *
     * The `whereNotIn(... MAX(id) group by ...)` subquery expresses "keep the
     * newest row per field" without a window function (ROW_NUMBER() /
     * PARTITION BY are not portable across sqlite/mysql/mariadb/pgsql/sqlsrv —
     * see .specs/draft/multiple-database-engines), so every supported engine
     * runs the exact same plan.
     *
     * Reads RevisionSetting::current()->retention_days (task 12's admin-
     * configurable singleton) rather than a raw config value, so lowering the
     * retention window in the admin panel (task 13) takes effect on the very
     * next scheduled prune without a deploy.
     */
    public function prunable(): Builder
    {
        return static::query()
            ->where('origin', RevisionOrigin::Automatic)
            ->whereNull('label')
            ->where('created_at', '<', now()->subDays(RevisionSetting::current()->retention_days))
            ->whereNotIn('id', function ($query) {
                $query->selectRaw('MAX(id)')
                    ->from('revisions')
                    ->groupBy(['revisionable_type', 'revisionable_id', 'field']);
            });
    }
}
