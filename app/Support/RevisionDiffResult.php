<?php

namespace App\Support;

use App\Services\Diff\DiffHtmlRenderer;
use App\Services\RevisionDiffer;

/**
 * The result of comparing two revisions of one field, returned by
 * {@see RevisionDiffer}.
 *
 * Both diff strategies produce the same shape, which is the point: the compare
 * view renders a Rich field and a Markdown field with the same two lines of
 * Blade, and never has to ask which differ ran.
 */
final readonly class RevisionDiffResult
{
    /**
     * @param  string  $html  The rendered diff, safe to `{!! !!}` directly — both
     *                        producers escape the underlying text themselves (see
     *                        {@see DiffHtmlRenderer} for why that has to be true
     *                        by construction).
     * @param  int  $changeCount  How many change *hunks* the diff found — a contiguous
     *                            run of changed blocks (or lines) counts once, which is
     *                            what a reader means by "3 changes". Zero means the two
     *                            values are identical.
     */
    public function __construct(
        public string $html,
        public int $changeCount,
    ) {}

    public function hasChanges(): bool
    {
        return $this->changeCount > 0;
    }
}
