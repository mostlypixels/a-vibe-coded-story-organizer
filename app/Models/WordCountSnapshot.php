<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One project's cumulative word count on one writer-day.
 *
 * `word_count` is a cumulative total, never a delta — a stored delta cannot
 * be reconciled after an edit to old text. Deltas are derived at read time
 * (see App\Services\WordCountHistory) and never stored.
 *
 * Not a revisionable: a snapshot is a derived figure, not authored content,
 * so it does not use HasRevisions.
 */
class WordCountSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'recorded_on',
        'word_count',
    ];

    /**
     * `recorded_on` stores a bare 'Y-m-d' date, which a `date` cast does not
     * give: Eloquent writes a date cast as 'Y-m-d 00:00:00'. Two formats in
     * one column break the day: the recorder upserts 'Y-m-d', so a row an
     * Eloquent save wrote for the same day misses the unique key and the day
     * gets two rows. A range query on 'Y-m-d' also drops its last day.
     */
    protected function recordedOn(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): CarbonImmutable => CarbonImmutable::parse($value)->startOfDay(),
            set: fn (CarbonImmutable|DateTimeInterface|string $value): string => CarbonImmutable::parse($value)->toDateString(),
        );
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
