<?php

namespace App\Services\Diff;

use App\Enums\DiffChange;
use App\Support\DiffBlock;
use App\Support\DiffSpan;
use App\Support\HtmlBlock;
use App\Support\InlineToken;
use App\Support\VisualDiff;
use Jfcherng\Diff\SequenceMatcher;

/**
 * Compares two rich-HTML values the way a reader would: paragraph by
 * paragraph, then word by word inside the paragraphs that actually changed.
 *
 * Two levels rather than one, mirroring MediaWiki's wikidiff2. A single
 * word-level diff over a whole field would happily "match" a word in the first
 * paragraph with the same word in the last, producing a shredded, unreadable
 * result — and it would be quadratic over the entire field instead of over one
 * block.
 *
 * The sequence matching itself is `jfcherng/php-sequence-matcher` (BSD-3),
 * already installed as a dependency of the `jfcherng/php-diff` this app uses
 * for Markdown fields. It is a plain Myers matcher over arrays of strings,
 * which is exactly the primitive needed; everything HTML-aware lives in
 * {@see HtmlTokenizer} (in) and {@see DiffHtmlRenderer} (out). That is why this
 * feature adds no new composer dependency — see expanded/diffing.md for the
 * library evaluation, including why the one mature HTML-diff package
 * (`caxy/php-htmldiff`, GPL-2.0) was rejected.
 *
 * Produces structure, never HTML.
 */
class VisualHtmlDiffer
{
    public function __construct(private readonly HtmlTokenizer $tokenizer) {}

    /**
     * Compare an old value against a new one.
     *
     * Either side may be null — a field that did not exist yet at one of the
     * two save points simply contributes no blocks, so the whole of the other
     * side reads as inserted (or removed).
     */
    public function diff(?string $old, ?string $new): VisualDiff
    {
        $oldBlocks = $this->tokenizer->tokenize($old);
        $newBlocks = $this->tokenizer->tokenize($new);

        $matcher = new SequenceMatcher(
            $this->matchKeys($oldBlocks),
            $this->matchKeys($newBlocks),
        );

        $blocks = [];
        $changeCount = 0;

        foreach ($matcher->getOpcodes() as [$operation, $oldStart, $oldEnd, $newStart, $newEnd]) {
            if ($operation === SequenceMatcher::OP_EQ) {
                // Matched blocks still differ if only their formatting moved:
                // matchKey() ignores marks precisely so that this case lands
                // here, where it can be reported as such, instead of surfacing
                // as an unrelated delete plus insert.
                $changeCount += $this->appendMatched($blocks, $oldBlocks, $newBlocks, $oldStart, $oldEnd, $newStart);

                continue;
            }

            // Every non-equal opcode is one hunk — one contiguous run of
            // changed blocks — which is the unit the history row counts
            // ("and 2 more changes"), not the number of blocks inside it.
            $changeCount++;

            match ($operation) {
                SequenceMatcher::OP_INS => $this->appendWhole($blocks, $newBlocks, $newStart, $newEnd, DiffChange::Inserted),
                SequenceMatcher::OP_DEL => $this->appendWhole($blocks, $oldBlocks, $oldStart, $oldEnd, DiffChange::Removed),
                default => $this->appendReplaced($blocks, $oldBlocks, $newBlocks, $oldStart, $oldEnd, $newStart, $newEnd),
            };
        }

        return new VisualDiff($blocks, $changeCount);
    }

    /**
     * @param  list<HtmlBlock>  $blocks
     * @return list<string>
     */
    private function matchKeys(array $blocks): array
    {
        return array_map(fn (HtmlBlock $block): string => $block->matchKey(), $blocks);
    }

    /**
     * Blocks the matcher paired up: unchanged, unless their mark signatures
     * disagree.
     *
     * @param  list<DiffBlock>  $blocks
     * @param  list<HtmlBlock>  $oldBlocks
     * @param  list<HtmlBlock>  $newBlocks
     * @return int How many of them were formatting-only changes.
     */
    private function appendMatched(array &$blocks, array $oldBlocks, array $newBlocks, int $oldStart, int $oldEnd, int $newStart): int
    {
        $formattingChanges = 0;

        for ($offset = 0; $offset < $oldEnd - $oldStart; $offset++) {
            $oldBlock = $oldBlocks[$oldStart + $offset];
            $newBlock = $newBlocks[$newStart + $offset];

            if ($oldBlock->signature === $newBlock->signature) {
                $blocks[] = new DiffBlock(DiffChange::Unchanged, $newBlock);

                continue;
            }

            $oldMarks = $this->marksOf($oldBlock);
            $newMarks = $this->marksOf($newBlock);

            $blocks[] = new DiffBlock(
                DiffChange::FormattingChanged,
                $newBlock,
                marksAdded: array_values(array_diff($newMarks, $oldMarks)),
                marksRemoved: array_values(array_diff($oldMarks, $newMarks)),
            );

            $formattingChanges++;
        }

        return $formattingChanges;
    }

    /**
     * A run of blocks that exists on one side only.
     *
     * @param  list<DiffBlock>  $blocks
     * @param  list<HtmlBlock>  $source
     */
    private function appendWhole(array &$blocks, array $source, int $start, int $end, DiffChange $change): void
    {
        for ($index = $start; $index < $end; $index++) {
            $blocks[] = new DiffBlock($change, $source[$index]);
        }
    }

    /**
     * A run of blocks replaced by another run.
     *
     * The two runs are paired up in order — old #1 against new #1, and so on —
     * and each pair gets the word-level pass. Any leftover block on either side
     * is a plain removal or insertion.
     *
     * > [!NOTE]
     * > A paragraph *moved* elsewhere in the field therefore reports as a
     * > removal plus an insertion rather than as a move. This is a deliberate,
     * > tested limitation: detecting moves means matching blocks across the
     * > whole document, which costs far more than it buys for prose that is
     * > edited in place far more often than it is rearranged.
     *
     * @param  list<DiffBlock>  $blocks
     * @param  list<HtmlBlock>  $oldBlocks
     * @param  list<HtmlBlock>  $newBlocks
     */
    private function appendReplaced(array &$blocks, array $oldBlocks, array $newBlocks, int $oldStart, int $oldEnd, int $newStart, int $newEnd): void
    {
        $pairs = min($oldEnd - $oldStart, $newEnd - $newStart);

        for ($offset = 0; $offset < $pairs; $offset++) {
            $oldBlock = $oldBlocks[$oldStart + $offset];
            $newBlock = $newBlocks[$newStart + $offset];
            $spans = $this->inlineSpans($oldBlock, $newBlock);

            if ($spans === null) {
                // Over the complexity cap: degrade this pair to "the old one
                // went, the new one arrived" rather than spend quadratic time
                // on a rewrite nobody could read word by word anyway.
                $blocks[] = new DiffBlock(DiffChange::Removed, $oldBlock);
                $blocks[] = new DiffBlock(DiffChange::Inserted, $newBlock);

                continue;
            }

            $blocks[] = new DiffBlock(DiffChange::Replaced, $newBlock, spans: $spans);
        }

        $this->appendWhole($blocks, $oldBlocks, $oldStart + $pairs, $oldEnd, DiffChange::Removed);
        $this->appendWhole($blocks, $newBlocks, $newStart + $pairs, $newEnd, DiffChange::Inserted);
    }

    /**
     * The word-level pass over one pair of blocks, or null when the pair is
     * over `revisions.diff.max_word_complexity` (see the config file for why
     * the ceiling exists).
     *
     * @return list<DiffSpan>|null
     */
    private function inlineSpans(HtmlBlock $oldBlock, HtmlBlock $newBlock): ?array
    {
        if (count($oldBlock->tokens) * count($newBlock->tokens) > (int) config('revisions.diff.max_word_complexity')) {
            return null;
        }

        $matcher = new SequenceMatcher(
            $this->comparables($oldBlock->tokens),
            $this->comparables($newBlock->tokens),
        );

        $spans = [];

        foreach ($matcher->getOpcodes() as [$operation, $oldStart, $oldEnd, $newStart, $newEnd]) {
            match ($operation) {
                SequenceMatcher::OP_EQ => $spans[] = new DiffSpan(DiffChange::Unchanged, array_slice($newBlock->tokens, $newStart, $newEnd - $newStart)),
                SequenceMatcher::OP_INS => $spans[] = new DiffSpan(DiffChange::Inserted, array_slice($newBlock->tokens, $newStart, $newEnd - $newStart)),
                SequenceMatcher::OP_DEL => $spans[] = new DiffSpan(DiffChange::Removed, array_slice($oldBlock->tokens, $oldStart, $oldEnd - $oldStart)),
                // A replacement reads as the old words struck out, then the new
                // ones — one marker per direction, which is what a reader can
                // actually follow.
                default => array_push(
                    $spans,
                    new DiffSpan(DiffChange::Removed, array_slice($oldBlock->tokens, $oldStart, $oldEnd - $oldStart)),
                    new DiffSpan(DiffChange::Inserted, array_slice($newBlock->tokens, $newStart, $newEnd - $newStart)),
                ),
            };
        }

        return $spans;
    }

    /**
     * @param  list<InlineToken>  $tokens
     * @return list<string>
     */
    private function comparables(array $tokens): array
    {
        return array_map(fn (InlineToken $token): string => $token->comparable(), $tokens);
    }

    /**
     * Every distinct mark in force anywhere in the block, so two blocks can be
     * asked which formatting arrived and which left.
     *
     * @return list<string>
     */
    private function marksOf(HtmlBlock $block): array
    {
        $marks = [];

        foreach ($block->tokens as $token) {
            foreach ($token->marks as $mark) {
                $marks[$mark] = true;
            }
        }

        return array_keys($marks);
    }
}
