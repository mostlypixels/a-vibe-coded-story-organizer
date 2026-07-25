<?php

namespace App\Services;

use App\Enums\FieldKind;
use App\Services\Diff\DiffHtmlRenderer;
use App\Services\Diff\HtmlTokenizer;
use App\Services\Diff\SourceDiffer;
use App\Services\Diff\VisualHtmlDiffer;
use App\Support\RevisionDiffResult;

/**
 * The one class the rest of the app asks "what changed between these two
 * values?" — and a router between the two strategies that answer it
 * (expanded/diffing.md, *Two diff strategies, chosen by `FieldKind`*).
 *
 * | `FieldKind`          | Strategy                                          |
 * |----------------------|---------------------------------------------------|
 * | `Rich`               | **Visual diff** — {@see VisualHtmlDiffer} + {@see DiffHtmlRenderer} |
 * | `Markdown` / `Plain` | **Source diff** — {@see SourceDiffer}              |
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
        private readonly SourceDiffer $sourceDiffer,
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
            : $this->sourceDiffer->diff($old ?? '', $new ?? '');
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
}
