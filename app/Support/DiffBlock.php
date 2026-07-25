<?php

namespace App\Support;

use App\Enums\DiffChange;
use App\Services\Diff\DiffHtmlRenderer;
use App\Services\Diff\VisualHtmlDiffer;

/**
 * One block of the two compared revisions, and what became of it.
 *
 * The output unit of {@see VisualHtmlDiffer} and the input unit of
 * {@see DiffHtmlRenderer}. Deliberately structure, not HTML: keeping the two
 * apart is what lets the renderer own escaping completely, and lets the
 * summarizer count and slice changes without parsing anything.
 */
final readonly class DiffBlock
{
    /**
     * @param  HtmlBlock  $block  The block to render. For a removal that is the old
     *                            side; for everything else, the new side — so a
     *                            reader always sees the current shape of surviving
     *                            content.
     * @param  list<DiffSpan>  $spans  Word runs, only for {@see DiffChange::Replaced}.
     * @param  list<string>  $marksAdded  Marks now in force that were not before, only
     *                                    for {@see DiffChange::FormattingChanged}.
     * @param  list<string>  $marksRemoved  …and the ones that went away.
     */
    public function __construct(
        public DiffChange $change,
        public HtmlBlock $block,
        public array $spans = [],
        public array $marksAdded = [],
        public array $marksRemoved = [],
    ) {}
}
