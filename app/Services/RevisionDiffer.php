<?php

namespace App\Services;

use App\Enums\FieldKind;
use App\Support\RevisionDiffResult;
use App\Support\RichText;
use Jfcherng\Diff\Differ;
use Jfcherng\Diff\DiffHelper;

/**
 * Compares two revisions' stored values and renders a reader-friendly diff
 * (handoff.md §5.3, expanded/architecture.md's "Compare — diff service").
 *
 * `jfcherng/php-diff` (composer.json) is the one dependency this class wraps —
 * confirmed on Packagist for this task (v7.0.1, released 2026-06-22, requires
 * only `php >=8.3`, no Laravel coupling) so the unverified-compatibility
 * warning `handoff.md` §6 flagged is resolved: it works, no hand-rolled
 * fallback was needed. `RevisionDiffer` is the only class the rest of the app
 * calls, so a future swap-out stays contained here.
 *
 * Rich fields are reduced to RichText::toPlainText() before diffing — a
 * writer comparing two rich-HTML descriptions wants to see prose changes, not
 * `</p><p>` tag churn (handoff.md §5.3's rejected alternative). Markdown and
 * plain fields diff their raw stored text directly, because there the markup
 * *is* the content the writer typed.
 */
class RevisionDiffer
{
    /**
     * Compare an "old" value against a "new" one for a field of the given kind.
     */
    public function diff(FieldKind $kind, ?string $old, ?string $new): RevisionDiffResult
    {
        $old ??= '';
        $new ??= '';

        if ($kind === FieldKind::Rich) {
            $oldProjection = RichText::toPlainText($old);
            $newProjection = RichText::toPlainText($new);

            // Same prose, different markup only (e.g. a bold mark toggled with no
            // wording change) — an empty word-level diff here would misleadingly
            // read as "nothing changed" even though the writer did save something.
            if ($oldProjection === $newProjection) {
                return RevisionDiffResult::formattingChangedOnly();
            }
        } else {
            // Markdown/plain: the raw stored text IS the content to diff.
            $oldProjection = $old;
            $newProjection = $new;
        }

        $html = DiffHelper::calculate(
            $oldProjection,
            $newProjection,
            'SideBySide',
            [
                // The whole field is one diff, not a multi-file patch — showing
                // every unchanged line keeps short-field context intact instead of
                // collapsing everything the writer didn't touch.
                'context' => Differ::CONTEXT_ALL,
            ],
            [
                // Word-level highlighting inside a changed line — a reader scanning
                // a paragraph rewrite wants to see which words moved, not just that
                // the whole line is red/green.
                'detailLevel' => 'word',
                'lineNumbers' => false,
                'showHeader' => false,
            ],
        );

        return RevisionDiffResult::diff($html);
    }
}
