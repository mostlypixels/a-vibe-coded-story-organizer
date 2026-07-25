<?php

namespace App\Support;

use App\Enums\FieldKind;
use App\Services\RevisionSummarizer;

/**
 * One field touched by one save point — a single row of the `revisions` table,
 * reduced to what a history list actually renders.
 *
 * Note what is *not* here: the stored `value`. A history list never loads it —
 * that is the whole reason {@see $summaryHtml} and {@see $changeCount} are
 * columns rather than something computed at render time (see
 * {@see RevisionSummarizer}), and why `size_bytes` exists to answer "how big
 * was it?" without hydrating a megabyte of scene contents.
 */
final readonly class SaveEntry
{
    /**
     * @param  int  $revisionId  The row this entry stands for — what a revert addresses.
     * @param  string|null  $summaryHtml  Already escaped and safe to `{!! !!}`; null on a
     *                                    baseline row, which has no predecessor to differ from.
     * @param  int  $changeCount  Change hunks, so the row can offer "and N−1 more changes".
     */
    public function __construct(
        public int $revisionId,
        public string $field,
        public FieldKind $kind,
        public ?string $summaryHtml,
        public int $changeCount,
        public int $sizeBytes,
    ) {}

    /**
     * Whether this entry has more changes than its summary shows — the trigger
     * for the "and N more changes" link through to the compare page.
     */
    public function hasMoreChanges(): bool
    {
        return $this->changeCount > 1;
    }

    /**
     * How many changes the summary does *not* show.
     */
    public function otherChangeCount(): int
    {
        return max(0, $this->changeCount - 1);
    }
}
