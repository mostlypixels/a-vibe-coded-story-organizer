<?php

namespace App\Models;

use App\Enums\ChallengeRecurrence;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A window plus a target. Words so far, par, and the verdict are derived
 * from `word_count_snapshots` at read time (see App\Support\ChallengeWindow
 * and App\Services\ChallengeProgress) — this row holds no progress.
 *
 * Not a revisionable: a challenge is a target, not authored content, so it
 * does not use HasRevisions.
 */
class Challenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'recurrence',
        'starts_on',
        'ends_on',
        'target_words',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'recurrence' => ChallengeRecurrence::class,
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
