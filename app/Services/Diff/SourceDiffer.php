<?php

namespace App\Services\Diff;

use App\Enums\DiffChange;
use App\Support\ChangeExcerpt;
use App\Support\DiffSpan;
use App\Support\InlineToken;
use App\Support\RevisionDiffResult;
use Jfcherng\Diff\Differ;
use Jfcherng\Diff\Factory\RendererFactory;
use Jfcherng\Diff\SequenceMatcher;

/**
 * Diffs author-written Markdown and plain text by line and then by word.
 *
 * > [!WARNING]
 * > Do not send rich HTML here. Its tags are not author-written source text.
 */
class SourceDiffer
{
    /**
     * The whole comparison as HTML, plus its hunk count.
     *
     * Safe to `{!! !!}`: `jfcherng`'s HTML renderer escapes the underlying text
     * itself, which is the same contract {@see DiffHtmlRenderer} holds up on the
     * rich side.
     */
    public function diff(string $old, string $new): RevisionDiffResult
    {
        $differ = $this->differ($old, $new);

        $html = RendererFactory::make('SideBySide', [
            // Word-level highlighting inside a changed line — a reader scanning
            // a paragraph rewrite wants to see which words moved, not just that
            // the whole line is red/green.
            'detailLevel' => 'word',
            'lineNumbers' => false,
            'showHeader' => false,
        ])->render($differ);

        // Counted *after* rendering on purpose: the Differ caches its opcodes,
        // so this reuses the comparison the renderer just triggered instead of
        // running a second one.
        return new RevisionDiffResult($html, $this->countChangedHunks($differ));
    }

    /**
     * The first changed hunk, as the run of words a summary is built from.
     *
     * The line diff says *where* the change is; the words inside that hunk are
     * then compared against each other so the summary can mark the handful of
     * words that actually moved rather than striking out two whole lines.
     */
    public function excerpt(string $old, string $new): ChangeExcerpt
    {
        $differ = $this->differ($old, $new);
        $changeCount = $this->countChangedHunks($differ);

        if ($changeCount === 0) {
            return ChangeExcerpt::unchanged();
        }

        foreach ($differ->getGroupedOpcodes() as $group) {
            foreach ($group as [$operation, $oldStart, $oldEnd, $newStart, $newEnd]) {
                if ($operation === SequenceMatcher::OP_EQ) {
                    continue;
                }

                return new ChangeExcerpt(
                    $this->wordSpans(
                        $this->words($differ->getOld($oldStart, $oldEnd)),
                        $this->words($differ->getNew($newStart, $newEnd)),
                    ),
                    $changeCount,
                );
            }
        }

        // Unreachable: a non-zero count means a non-equal opcode exists. Kept
        // so the method has one honest exit rather than an implicit null.
        return ChangeExcerpt::unchanged();
    }

    /**
     * One `Differ` over the two values, split into lines.
     */
    private function differ(string $old, string $new): Differ
    {
        return new Differ(
            explode("\n", $old),
            explode("\n", $new),
            [
                // The whole field is one diff, not a multi-file patch — showing
                // every unchanged line keeps short-field context intact instead
                // of collapsing everything the writer didn't touch.
                'context' => Differ::CONTEXT_ALL,
            ],
        );
    }

    /**
     * One hunk per non-equal opcode — the same unit {@see VisualHtmlDiffer}
     * counts, so `changeCount` means the same thing whichever strategy ran.
     */
    private function countChangedHunks(Differ $differ): int
    {
        $hunks = 0;

        foreach ($differ->getGroupedOpcodes() as $group) {
            foreach ($group as [$operation]) {
                if ($operation !== SequenceMatcher::OP_EQ) {
                    $hunks++;
                }
            }
        }

        return $hunks;
    }

    /**
     * The words of a run of lines, with the line breaks flattened away — a
     * summary is one line, so where the change sat in the paragraph does not
     * survive into it.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function words(array $lines): array
    {
        return preg_split('/\s+/u', implode(' ', $lines), flags: PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Compare two word streams and group the result into runs that share one
     * fate, which is what a reader sees: "three words were replaced" is one
     * marker, not three.
     *
     * @param  list<string>  $oldWords
     * @param  list<string>  $newWords
     * @return list<DiffSpan>
     */
    private function wordSpans(array $oldWords, array $newWords): array
    {
        $matcher = new SequenceMatcher($oldWords, $newWords);
        $spans = [];

        foreach ($matcher->getOpcodes() as [$operation, $oldStart, $oldEnd, $newStart, $newEnd]) {
            match ($operation) {
                SequenceMatcher::OP_EQ => $spans[] = $this->span(DiffChange::Unchanged, $newWords, $newStart, $newEnd),
                SequenceMatcher::OP_INS => $spans[] = $this->span(DiffChange::Inserted, $newWords, $newStart, $newEnd),
                SequenceMatcher::OP_DEL => $spans[] = $this->span(DiffChange::Removed, $oldWords, $oldStart, $oldEnd),
                // A replacement reads as the old words struck out, then the new
                // ones — one marker per direction, which is what a reader can
                // actually follow.
                default => array_push(
                    $spans,
                    $this->span(DiffChange::Removed, $oldWords, $oldStart, $oldEnd),
                    $this->span(DiffChange::Inserted, $newWords, $newStart, $newEnd),
                ),
            };
        }

        return $spans;
    }

    /**
     * @param  list<string>  $words
     */
    private function span(DiffChange $change, array $words, int $start, int $end): DiffSpan
    {
        // No marks: source fields carry no inline formatting the differ knows
        // about. Whatever markup they contain is text, and stays text.
        $tokens = array_map(
            fn (string $word): InlineToken => new InlineToken($word),
            array_slice($words, $start, $end - $start),
        );

        return new DiffSpan($change, array_values($tokens));
    }
}
