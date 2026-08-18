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
 * closes — a still-open row is overwritten via a plain UPDATE in
 * App\Services\RevisionRecorder, not Eloquent's dirty-tracking `save()`.
 * `$timestamps` is disabled because there is no `updated_at` column at all;
 * `created_at` is always set explicitly by the writer, never left to the
 * database default.
 *
 * `save_id` groups the per-field rows written by one Save (or one autosave
 * burst) into a single *save point* — the unit the history, compare and revert
 * screens address. Storage stays per field; only the layers above it think in
 * save points. `summary_html` and `change_count` are that row's diff against
 * its predecessor, computed once at write time so no list page ever diffs
 * anything at read time.
 *
 * `project_id` is a real foreign key (not inferred from the polymorphic
 * `revisionable_type`/`revisionable_id` pair) because deleting a Project
 * cascades to its acts/chapters/scenes at the DB level without firing
 * Eloquent events — a `deleting` hook here would silently never run. See
 * documentation/architecture.md → "Revisions".
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
        'save_id',
        'value',
        'size_bytes',
        'summary_html',
        'change_count',
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
     * eager-loads this and selects only `id`/`name`. It never pulls in anything
     * from `revisions.value`.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The query {@see MassPrunable} runs (via the scheduled `model:prune`
     * command) to delete eligible rows in bulk.
     *
     * This is the single most safety-critical query in the whole feature. It
     * must delete an "automatic", unlabeled row once it is older than the
     * retention window, *unless* that row is the newest
     * revision recorded for its (revisionable_type, revisionable_id, field)
     * triple — losing the only remaining history for a field would be a real
     * data loss, not just tidying.
     *
     * "Newest" is `(created_at, id)`, the same ordering the history list, the
     * snapshot and the reverter all walk — so the row this refuses to delete is
     * exactly the one a reader sees as current.
     *
     * > [!WARNING]
     * > Insertion order cannot identify the newest row. Baselines use an earlier
     * > timestamp and can have a later ID.
     *
     * Expressed as "a strictly newer sibling exists" rather than as a grouped
     * subquery: it is the same portable SQL shape (no ROW_NUMBER() / PARTITION
     * BY — not portable across sqlite/mysql/mariadb/pgsql/sqlsrv), it states the
     * rule the way the docblock above states it, and the existing
     * `(revisionable_type, revisionable_id, field, created_at)` index serves it.
     *
     * The sibling lookup is deliberately unfiltered by origin or label: a manual
     * or labeled revision newer than an automatic one still means that automatic
     * row is not the field's current version, so it may be pruned.
     *
     * Reads RevisionSetting::current()->retention_days, the admin-configurable
     * singleton, rather than a raw config value. A lower retention window set
     * in the admin panel takes effect on the next scheduled prune, with no
     * deploy.
     */
    public function prunable(): Builder
    {
        return static::query()
            ->where('origin', RevisionOrigin::Automatic)
            ->whereNull('label')
            ->where('created_at', '<', now()->subDays(RevisionSetting::current()->retention_days))
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('revisions as newer_revisions')
                    ->whereColumn('newer_revisions.revisionable_type', 'revisions.revisionable_type')
                    ->whereColumn('newer_revisions.revisionable_id', 'revisions.revisionable_id')
                    ->whereColumn('newer_revisions.field', 'revisions.field')
                    ->where(fn ($newer) => $newer
                        ->whereColumn('newer_revisions.created_at', '>', 'revisions.created_at')
                        ->orWhere(fn ($tie) => $tie
                            ->whereColumn('newer_revisions.created_at', 'revisions.created_at')
                            ->whereColumn('newer_revisions.id', '>', 'revisions.id')));
            });
    }
}
