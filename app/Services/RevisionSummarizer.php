<?php

namespace App\Services;

use App\Enums\DiffChange;
use App\Enums\FieldKind;
use App\Services\Diff\DiffHtmlRenderer;
use App\Services\Diff\SourceDiffer;
use App\Services\Diff\VisualHtmlDiffer;
use App\Support\ChangeExcerpt;
use App\Support\DiffBlock;
use App\Support\DiffSpan;
use App\Support\RevisionSummary;

/**
 * Answers "what did this save change?" in one line, for a history row.
 *
 * Called by {@see RevisionRecorder} at **write** time and never at read time: a
 * diff between two immutable revisions is a constant, so it is computed once on
 * the way in and stored on the row (`summary_html`, `change_count`). A page of
 * history therefore renders without diffing anything.
 *
 * It runs the same two engines {@see RevisionDiffer} routes between, so a row's
 * summary and the compare page it links to can never describe the same save
 * differently.
 *
 * ## Why the summary is bounded by length, not by hunk count
 *
 * A find-and-replace on a character's name produces forty hunks. Forty hunks in
 * a list row is unreadable; "the first hunk only" on a one-word change is
 * uselessly terse. So the row shows the *first* change with as much of its
 * surroundings as fits in `config('revisions.summary.max_length')` characters
 * of text — markup never counts against the budget — and `change_count` carries
 * the rest as "and N more changes".
 *
 * The budget is spent from the change outward, never from the top of the field
 * down, so the thing the row exists to show can never be the part that got cut.
 */
class RevisionSummarizer
{
    /**
     * How much of the budget goes to the unchanged words *before* the change,
     * as a divisor: a quarter of it, so the reader gets a running start into
     * the sentence without the change itself being pushed off the end.
     */
    private const LEADING_CONTEXT_DIVISOR = 4;

    /** Marks an end that was cut off — the row is an excerpt, and says so. */
    private const ELLIPSIS = '…';

    public function __construct(
        private readonly VisualHtmlDiffer $visualDiffer,
        private readonly SourceDiffer $sourceDiffer,
        private readonly DiffHtmlRenderer $renderer,
    ) {}

    /**
     * Summarise one field's new value against whatever it replaced.
     *
     * A null `$previousValue` means this row *is* the start of the field's
     * history — there is nothing to compare it against, so it summarises as a
     * baseline rather than as a change.
     */
    public function summarize(FieldKind $kind, ?string $previousValue, string $newValue): RevisionSummary
    {
        if ($previousValue === null) {
            return RevisionSummary::baseline();
        }

        $excerpt = $kind === FieldKind::Rich
            ? $this->richExcerpt($previousValue, $newValue)
            : $this->sourceDiffer->excerpt($previousValue, $newValue);

        return new RevisionSummary(
            $excerpt->spans === [] ? null : $this->render($excerpt->spans),
            $excerpt->changeCount,
        );
    }

    /**
     * The rich-field path: diff the document, then summarise the first block
     * that changed.
     */
    private function richExcerpt(string $previousValue, string $newValue): ChangeExcerpt
    {
        $diff = $this->visualDiffer->diff($previousValue, $newValue);
        $block = $this->blockToShow($diff->changedBlocks());

        if ($block === null) {
            return ChangeExcerpt::unchanged();
        }

        return new ChangeExcerpt($this->spansOf($block), $diff->changeCount);
    }

    /**
     * Which changed block the row leads with.
     *
     * Normally the first one — but a formatting-only block has no changed words
     * to mark, so leading with one would show the reader a line of unmarked
     * text. When some other block did change words, that is the more useful
     * thing to show; when none did, the formatting-only block at least points
     * at the paragraph that moved.
     *
     * @param  list<DiffBlock>  $changedBlocks
     */
    private function blockToShow(array $changedBlocks): ?DiffBlock
    {
        foreach ($changedBlocks as $block) {
            if ($block->change !== DiffChange::FormattingChanged) {
                return $block;
            }
        }

        return $changedBlocks[0] ?? null;
    }

    /**
     * One changed block as the run of word spans a summary is built from.
     *
     * @return list<DiffSpan>
     */
    private function spansOf(DiffBlock $diffBlock): array
    {
        // A replaced block already carries its word-level runs; the others
        // changed as a whole, so their entire word stream shares one fate.
        if ($diffBlock->change === DiffChange::Replaced) {
            return $diffBlock->spans;
        }

        $change = match ($diffBlock->change) {
            DiffChange::Inserted => DiffChange::Inserted,
            DiffChange::Removed => DiffChange::Removed,
            // Formatting-only: the words are identical on both sides, so there
            // is nothing to strike out or underline.
            default => DiffChange::Unchanged,
        };

        return [new DiffSpan($change, $diffBlock->block->tokens)];
    }

    /**
     * Cut the excerpt down to the configured length and render it.
     *
     * Cutting happens here, on *words*, and never on the rendered HTML: trimming
     * a marked-up string risks leaving an `<ins>` open, while trimming a span's
     * tokens cannot — the renderer closes whatever it opens.
     *
     * @param  list<DiffSpan>  $spans
     */
    private function render(array $spans): string
    {
        $maxLength = (int) config('revisions.summary.max_length');
        $anchor = $this->firstChangedIndex($spans);

        $before = array_slice($spans, 0, $anchor);
        $from = array_slice($spans, $anchor);

        $leading = $this->take($before, intdiv($maxLength, self::LEADING_CONTEXT_DIVISOR), fromEnd: true);
        $trailing = $this->take($from, $maxLength - $this->length($leading), fromEnd: false);

        return implode('', [
            $this->length($leading) < $this->length($before) ? self::ELLIPSIS.' ' : '',
            $this->renderer->renderSpanRun([...$leading, ...$trailing]),
            $this->length($trailing) < $this->length($from) ? ' '.self::ELLIPSIS : '',
        ]);
    }

    /**
     * Where the change starts, which is where the budget is spent from. Falls
     * back to the beginning for an all-unchanged run (a formatting-only block).
     *
     * @param  list<DiffSpan>  $spans
     */
    private function firstChangedIndex(array $spans): int
    {
        foreach ($spans as $index => $span) {
            if ($span->change->isChange()) {
                return $index;
            }
        }

        return 0;
    }

    /**
     * As many whole words as fit in `$maxLength` characters, taken from the
     * start of the run or — for the context that *precedes* a change — from its
     * end. Spans keep their change status, so a trimmed removal is still a
     * removal.
     *
     * @param  list<DiffSpan>  $spans
     * @param  bool  $fromEnd  Read the run backwards, keeping the words nearest the change.
     * @return list<DiffSpan>
     */
    private function take(array $spans, int $maxLength, bool $fromEnd): array
    {
        $taken = [];
        $remaining = $maxLength;
        $exhausted = false;

        foreach ($fromEnd ? array_reverse($spans) : $spans as $span) {
            $kept = [];

            foreach ($fromEnd ? array_reverse($span->tokens) : $span->tokens as $token) {
                $isFirstWord = $taken === [] && $kept === [];

                // Every word but the first also pays for the space it is joined
                // with, so the budget matches the rendered text exactly.
                $cost = mb_strlen($token->word) + ($isFirstWord ? 0 : 1);

                // Reading forwards, the first word is kept whatever it costs:
                // an over-long word must not reduce a real change to an empty
                // summary. Leading context has no such claim — it is optional.
                if ($cost > $remaining && ! ($isFirstWord && ! $fromEnd)) {
                    $exhausted = true;

                    break;
                }

                $remaining -= $cost;
                $kept[] = $token;
            }

            if ($kept !== []) {
                $taken[] = new DiffSpan($span->change, $fromEnd ? array_reverse($kept) : $kept);
            }

            if ($exhausted) {
                break;
            }
        }

        return $fromEnd ? array_reverse($taken) : $taken;
    }

    /**
     * How long a run of spans reads once rendered — its words joined by single
     * spaces, exactly as {@see DiffHtmlRenderer} joins them. Text only: the
     * markup around it is not the reader's budget to pay.
     *
     * @param  list<DiffSpan>  $spans
     */
    private function length(array $spans): int
    {
        $words = [];

        foreach ($spans as $span) {
            foreach ($span->tokens as $token) {
                $words[] = $token->word;
            }
        }

        return $words === [] ? 0 : mb_strlen(implode(' ', $words));
    }
}
