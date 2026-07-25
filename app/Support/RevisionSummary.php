<?php

namespace App\Support;

use App\Services\RevisionSummarizer;

/**
 * What one revision row says about itself on a history list, computed once at
 * write time by {@see RevisionSummarizer} and stored on the row.
 *
 * The two columns it fills (`revisions.summary_html`, `revisions.change_count`)
 * exist so that rendering a page of history never diffs anything: a diff
 * between two immutable revisions is a constant, so it is computed on the way
 * in rather than on every way out.
 */
final readonly class RevisionSummary
{
    /**
     * @param  string|null  $summaryHtml  An excerpt of the change, already escaped and
     *                                    carrying only `<ins>`/`<del>` — null when there
     *                                    is nothing to summarise (a baseline row, or two
     *                                    identical values).
     * @param  int  $changeCount  Change hunks in the whole comparison, so a row can say
     *                            "and N−1 more changes" and link to the compare page.
     */
    public function __construct(
        public ?string $summaryHtml,
        public int $changeCount,
    ) {}

    /**
     * A row with nothing before it. It is not "no changes" so much as "no
     * predecessor to compare against" — the history list renders these as
     * *Initial value* rather than as an empty summary.
     */
    public static function baseline(): self
    {
        return new self(null, 0);
    }
}
