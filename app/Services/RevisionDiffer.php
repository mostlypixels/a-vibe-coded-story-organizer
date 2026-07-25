<?php

namespace App\Services;

use App\Enums\FieldKind;
use App\Services\Diff\DiffHtmlRenderer;
use App\Services\Diff\HtmlTokenizer;
use App\Services\Diff\VisualHtmlDiffer;
use App\Support\RevisionDiffResult;
use Jfcherng\Diff\Differ;
use Jfcherng\Diff\Factory\RendererFactory;
use Jfcherng\Diff\SequenceMatcher;

/**
 * The one class the rest of the app asks "what changed between these two
 * values?" — and a router between the two strategies that answer it
 * (expanded/diffing.md, *Two diff strategies, chosen by `FieldKind`*).
 *
 * | `FieldKind`          | Strategy                                          |
 * |----------------------|---------------------------------------------------|
 * | `Rich`               | **Visual diff** — {@see VisualHtmlDiffer} + {@see DiffHtmlRenderer} |
 * | `Markdown` / `Plain` | **Source diff** — `jfcherng/php-diff`, side by side |
 *
 * The split is about who authored the markup. A writer never types the TipTap
 * HTML behind a rich field, so `<strong>` moving is "bolded", not a tag change,
 * and she should see the prose the way she wrote it. She *does* type the
 * Markdown in `Scene.contents` — there the markup **is** the content, so the
 * raw stored text is what gets diffed.
 *
 * > [!WARNING]
 * > Never route a Markdown or Plain field through the sanitizer or
 * > {@see HtmlTokenizer}. Those fields are not HTML, and tokenizing them would
 * > silently eat the very characters (`**`, `#`, `>`) the writer changed.
 *
 * Keeping both strategies behind this one signature is deliberate: every call
 * site passes a `FieldKind` and gets a {@see RevisionDiffResult} back, so a
 * future swap of either engine stays contained in this file.
 */
class RevisionDiffer
{
    public function __construct(
        private readonly VisualHtmlDiffer $visualDiffer,
        private readonly DiffHtmlRenderer $renderer,
    ) {}

    /**
     * Compare an "old" value against a "new" one for a field of the given kind.
     *
     * Either side may be null — a field that had no value yet at one of the two
     * points in time reads as wholly inserted (or removed).
     */
    public function diff(FieldKind $kind, ?string $old, ?string $new): RevisionDiffResult
    {
        return $kind === FieldKind::Rich
            ? $this->visualDiff($old, $new)
            : $this->sourceDiff($old ?? '', $new ?? '');
    }

    /**
     * Rich fields: diff the document structure, render it back as HTML that
     * looks like the field itself with the changes marked in place.
     */
    private function visualDiff(?string $old, ?string $new): RevisionDiffResult
    {
        $diff = $this->visualDiffer->diff($old, $new);

        return new RevisionDiffResult(
            $this->renderer->render($diff->blocks),
            $diff->changeCount,
        );
    }

    /**
     * Markdown and plain fields: the stored text is what the writer typed, so
     * `jfcherng/php-diff` compares it verbatim and lays the two versions out
     * side by side.
     */
    private function sourceDiff(string $old, string $new): RevisionDiffResult
    {
        $differ = new Differ(
            explode("\n", $old),
            explode("\n", $new),
            [
                // The whole field is one diff, not a multi-file patch — showing
                // every unchanged line keeps short-field context intact instead
                // of collapsing everything the writer didn't touch.
                'context' => Differ::CONTEXT_ALL,
            ],
        );

        $html = RendererFactory::make('SideBySide', [
            // Word-level highlighting inside a changed line — a reader scanning
            // a paragraph rewrite wants to see which words moved, not just that
            // the whole line is red/green.
            'detailLevel' => 'word',
            'lineNumbers' => false,
            'showHeader' => false,
        ])->render($differ);

        // Asked *after* rendering on purpose: the Differ caches its opcodes, so
        // counting here reuses the comparison the renderer just triggered
        // instead of running a second one.
        return new RevisionDiffResult($html, $this->countChangedHunks($differ));
    }

    /**
     * One hunk per non-equal opcode — the same unit {@see VisualHtmlDiffer}
     * counts for rich fields, so `changeCount` means the same thing whichever
     * strategy produced it.
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
}
