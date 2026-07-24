<?php

namespace App\Support;

/**
 * The result of comparing two revisions of one field, returned by
 * App\Services\RevisionDiffer. Exactly one of the two states applies:
 *
 *   - A real diff: `html` holds the rendered word-level diff markup, safe to
 *     `{!! !!}` directly (jfcherng/php-diff's HTML renderer already escapes the
 *     underlying text).
 *   - "Formatting changed only": the two revisions' RichText::toPlainText()
 *     projections are identical, but the raw stored HTML differs (handoff.md
 *     §5.3) — rendering an empty diff here would read as "nothing changed",
 *     which is misleading when the writer *did* press Save and something *did*
 *     change (e.g. a bold mark added/removed with no wording change).
 */
final class RevisionDiffResult
{
    private function __construct(
        public readonly bool $formattingChangedOnly,
        public readonly ?string $html,
    ) {}

    public static function diff(string $html): self
    {
        return new self(formattingChangedOnly: false, html: $html);
    }

    public static function formattingChangedOnly(): self
    {
        return new self(formattingChangedOnly: true, html: null);
    }
}
