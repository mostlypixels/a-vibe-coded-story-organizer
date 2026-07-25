<?php

namespace App\Support;

use App\Services\Diff\VisualHtmlDiffer;

/**
 * The whole result of comparing two rich-HTML values: every block in reading
 * order (unchanged ones included, so a diff reads as a document rather than as
 * a list of fragments) plus how many of them changed.
 *
 * @see VisualHtmlDiffer
 */
final readonly class VisualDiff
{
    /**
     * @param  list<DiffBlock>  $blocks
     * @param  int  $changeCount  Change *hunks* — a contiguous run of changed blocks counts
     *                            once, and each formatting-only block counts once. This is
     *                            the "and 2 more changes" a history row reports, stored as
     *                            `revisions.change_count` so no list page ever has to diff
     *                            anything to count them. Not the same as
     *                            `count(changedBlocks())`, which counts blocks.
     */
    public function __construct(
        public array $blocks,
        public int $changeCount,
    ) {}

    public function hasChanges(): bool
    {
        return $this->changeCount > 0;
    }

    /**
     * Only the blocks that changed, in reading order.
     *
     * @return list<DiffBlock>
     */
    public function changedBlocks(): array
    {
        return array_values(array_filter($this->blocks, fn (DiffBlock $block): bool => $block->change->isChange()));
    }
}
