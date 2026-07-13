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
 * documentation/architecture.md and .specs/.../data-model.md.
 */
class Import extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'archive_path',
        'archive_original_name',
        'phase',
        'id_maps',
        'queued',
        'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'phase' => ImportPhase::class,
            'id_maps' => 'array',
            'queued' => 'boolean',
        ];
    }

    /**
     * The importing user (owner). Drives ImportPolicy's ownership check.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The Project being built. Nullable — there is no project yet while the
     * import sits at phase = pending, and it becomes null again if that project
     * is deleted out from under an orphaned import row (nullOnDelete).
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * A human-friendly name for the in-progress-imports list (task 08). Falls
     * back to a generic label if the original upload filename was somehow not
     * recorded, so the list never renders a blank line.
     */
    public function archiveOriginalName(): string
    {
        return $this->archive_original_name ?: __('Uploaded archive');
    }
}
