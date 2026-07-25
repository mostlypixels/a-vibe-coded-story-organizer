<?php

namespace App\Support;

use App\Services\RevisionSummarizer;

/**
 * The first thing that changed between two values, plus how many changes there
 * were in all — the raw material {@see RevisionSummarizer} turns into a history
 * row's one-line summary.
 *
 * It exists so both diff strategies can hand the summarizer the same shape.
 * A rich field's excerpt comes from the visual differ's first changed block; a
 * Markdown or plain field's comes from the first changed hunk of the source
 * diff. Once they are both a run of spans, windowing, truncation and rendering
 * are written once instead of twice.
 *
 * Deliberately *not* rendered yet: the summarizer still has to cut it down to a
 * readable length, and cutting words is safe where cutting HTML is not.
 */
final readonly class ChangeExcerpt
{
    /**
     * @param  list<DiffSpan>  $spans  The changed words with whatever unchanged words
     *                                 surround them, in reading order. Empty when the
     *                                 two values are identical.
     * @param  int  $changeCount  Change hunks across the *whole* comparison, not just
     *                            this excerpt — the "and 3 more changes" a row reports.
     */
    public function __construct(
        public array $spans,
        public int $changeCount,
    ) {}

    public static function unchanged(): self
    {
        return new self([], 0);
    }
}
