<?php

namespace App\Models;

use App\Enums\ImportPhase;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One import attempt — the checkpoint/tracking record for a project import.
 *
 * Distinct from {@see ImportSetting} (the global settings singleton): this is
 * per-attempt state. `phase` records the last completed {@see ImportPhase} and
 * `id_maps` the accumulated id-remapping arrays, both checkpointed after each
 * phase commits so a stalled import can resume or be discarded. See
 * documentation/export-import/archive-format.md and .specs/.../data-model.md.
 */
class Import extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'archive_path',
        'archive_original_name',
        'archive_sha256',
        'phase',
        'id_maps',
        'queued_at',
        'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'phase' => ImportPhase::class,
            'id_maps' => 'array',
            'running_since' => 'datetime',
            'queued_at' => 'datetime',
        ];
    }

    /**
     * The importing user (owner). Drives ImportPolicy's ownership check.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The Project being built. Nullable — there is no project yet while the
     * import sits at phase = pending, and it becomes null again if that project
     * is deleted out from under an orphaned import row (nullOnDelete).
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * True while a run holds a fresh claim. A claim older than
     * `import.timeout_seconds` is a crashed run and does not count.
     */
    public function isRunning(): bool
    {
        return $this->running_since !== null
            && $this->running_since->gt(now()->subSeconds(config('import.timeout_seconds')));
    }

    /** True when a job waits for a worker: queued, not started, and not finished. */
    public function isQueued(): bool
    {
        return $this->queued_at !== null
            && ! $this->isRunning()
            && ! in_array($this->phase, [ImportPhase::Completed, ImportPhase::Failed], true);
    }

    /**
     * The row state the import page shows: completed, running, queued or stalled.
     * A stalled row waits for Resume or Discard.
     */
    public function state(): string
    {
        return match (true) {
            $this->phase === ImportPhase::Completed => 'completed',
            $this->isRunning() => 'running',
            $this->isQueued() => 'queued',
            default => 'stalled',
        };
    }

    /**
     * The earlier completed import of the same archive, while its project exists.
     * Only a row that never started has a duplicate: a started row is the writer's choice.
     */
    public function duplicateOf(): ?self
    {
        if ($this->archive_sha256 === null
            || $this->phase !== ImportPhase::Pending
            || $this->queued_at !== null
            || $this->running_since !== null
            || $this->failure_message !== null) {
            return null;
        }

        return self::query()
            ->where('user_id', $this->user_id)
            ->where('archive_sha256', $this->archive_sha256)
            ->where('phase', ImportPhase::Completed)
            ->whereNotNull('project_id')
            ->whereKeyNot($this->getKey())
            ->latest('updated_at')
            ->first();
    }

    /**
     * A human-friendly name for the in-progress-imports list. Falls
     * back to a generic label if the original upload filename was somehow not
     * recorded, so the list never renders a blank line.
     */
    public function archiveOriginalName(): string
    {
        return $this->archive_original_name ?: __('Uploaded archive');
    }
}
