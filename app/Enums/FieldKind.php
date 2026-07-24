<?php

namespace App\Enums;

use App\Support\AutosavableFields;

/**
 * How a registered autosavable field (see {@see AutosavableFields}) is
 * validated and rendered. Each kind maps to one existing validation rule so the
 * autosave endpoint never invents a parallel rule set:
 *
 *   - Rich: rich-HTML, routed through SanitizesRichHtml on write and validated with
 *     the SanitizeHtml rule (see App\Support\RichTextFields — the rich-field list
 *     itself still lives there, this enum only labels the kind).
 *   - Markdown: raw Markdown text, validated with ValidMarkdown, never sanitized
 *     (Scene.contents renders via Str::markdown() at read time instead).
 *   - Plain: a bare string with no markup at all, e.g. Project.rights (today a raw
 *     <textarea>). The one kind this feature introduces that didn't exist before.
 */
enum FieldKind: string
{
    case Rich = 'rich';
    case Markdown = 'markdown';
    case Plain = 'plain';
}
