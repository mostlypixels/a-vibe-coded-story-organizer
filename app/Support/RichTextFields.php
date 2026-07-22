<?php

namespace App\Support;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;

/**
 * Single source of truth for the rich-HTML text feature (guidelines: centralize
 * reference data in app/Support, no magic-string field lists scattered across
 * views/requests/models). Mirrors PlotlineColors / CodexMediaRules.
 *
 * It answers two questions in one place:
 *   1. Which model+field pairs are rich HTML (drives the set-mutators wired in
 *      task 02, the SanitizeHtml Form Request rules, and the x-wysiwyg/x-rich-text
 *      views in tasks 03/04).
 *   2. What the HtmlSanitizer allow-list is (tags, attributes, URL schemes). This
 *      MUST stay a superset of what the editor's slash menu can produce.
 *
 * Scene.contents is deliberately absent: it stays Markdown-only (ValidMarkdown +
 * Str::markdown()), never routed through the sanitizer. It IS edited through the
 * same x-wysiwyg editor as every rich-HTML field, in its `markdown` mode
 * (scenes/edit.blade.php renders `<x-wysiwyg ... markdown>`) — only the sanitizer
 * is bypassed, not the editor.
 */
class RichTextFields
{
    /**
     * The rich-HTML field list, keyed by model class.
     *
     * @var array<class-string, list<string>>
     */
    public const FIELDS = [
        Project::class => ['description'],
        Act::class => ['description'],
        Chapter::class => ['description'],
        Plotline::class => ['description'],
        Event::class => ['description'],
        Scene::class => ['description', 'notes'],
        CodexEntry::class => ['description'],
    ];

    /**
     * Tags the sanitizer permits. Everything else is stripped. Deliberately no
     * <script>/<iframe>/<object> and no presentational attributes (style/class) on
     * any tag. Kept in sync with the editor slash menu (task 04 of expand-tip-tap).
     *
     * `table`/`thead`/`tbody`/`tr`/`th`/`td` (tables), `img` (image references —
     * uploading new images is still out of scope, only referencing an existing URL),
     * and `label`/`input`/`span`/`div` (the task-list checkbox markup TipTap's
     * TaskItem/TaskList extensions render — see ALLOWED_ATTRIBUTES for the attributes
     * each of those carries) were added by the expand-tip-tap feature.
     *
     * @var list<string>
     */
    public const ALLOWED_TAGS = [
        'p', 'h1', 'h2', 'h3', 'h4', 'strong', 'em', 'u', 's',
        'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'a', 'br', 'hr',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'img',
        'label', 'input', 'span', 'div',
    ];

    /**
     * Attributes each tag carries in the sanitized output, keyed by tag name. A tag
     * absent from this map is allowed bare (no attributes at all) — see
     * purifierAllowedHtml(), which falls back to the tag name alone in that case.
     *
     * Note this only restricts which attribute *names* survive, not their values
     * (HTMLPurifier's `HTML.Allowed` directive has no per-attribute value
     * restriction beyond `URI.AllowedSchemes` for href/src) — e.g. `input`'s `type`
     * attribute isn't pinned to `checkbox` here; that's an accepted limitation of
     * this mechanism, not an oversight.
     *
     * @var array<string, list<string>>
     */
    public const ALLOWED_ATTRIBUTES = [
        'a' => ['href'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        // TipTap's TaskList/TaskItem (@tiptap/extension-list) render:
        //   <ul data-type="taskList"><li data-type="taskItem" data-checked="true">
        //     <label><input type="checkbox" checked></label><span></span><div>…</div>
        //   </li></ul>
        'ul' => ['data-type'],
        'li' => ['data-type', 'data-checked'],
        // `disabled` is added alongside type/checked because GFM's rendered task-list
        // (league/commonmark's TaskList extension, used by Str::markdown() for the
        // Scene.contents Markdown carve-out and checked here by
        // ContentSanitizer::assertMarkdownAllowed()) emits a static, read-only
        // <input disabled type="checkbox">, distinct from TipTap's own editable
        // checkbox markup (which never sets it). Not a security concern either way.
        'input' => ['type', 'checked', 'disabled'],
        // colspan/rowspan (expand-tip-tap task 04, table merge/split — HTML-mode
        // fields only) are structural, not presentational: without them a merged
        // cell's HTML would render with the wrong grid shape entirely. Note the
        // editor's own Table override (resources/js/wysiwyg.js's PlainTable) strips
        // the extension's default `style`/<colgroup>/<col> output before it ever
        // reaches the sanitizer, so no exception for `style` is needed here — this
        // app deliberately never emits it for tables.
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
        // The callout node (`> [!NOTE]` etc., expand-tip-tap task 06) presents over
        // the existing <blockquote> element via this attribute — no new tag needed.
        'blockquote' => ['data-callout-type'],
    ];

    /**
     * URL schemes allowed on <a href> and <img src>. Relative URLs carry no scheme
     * and remain permitted; javascript: and data: are blocked by omission.
     *
     * @var list<string>
     */
    public const ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * The flat list of rich-HTML fields as "Model.field" strings.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $fields = [];

        foreach (self::FIELDS as $model => $names) {
            $short = class_basename($model);

            foreach ($names as $name) {
                $fields[] = "{$short}.{$name}";
            }
        }

        return $fields;
    }

    /**
     * The rich-HTML field names for a given model class.
     *
     * @param  class-string  $model
     * @return list<string>
     */
    public static function forModel(string $model): array
    {
        return self::FIELDS[$model] ?? [];
    }

    /**
     * Whether the given model+field is a rich-HTML field.
     *
     * @param  class-string  $model
     */
    public static function isRich(string $model, string $field): bool
    {
        return in_array($field, self::forModel($model), true);
    }

    /**
     * The HTMLPurifier "HTML.Allowed" directive built from the tag allow-list plus
     * ALLOWED_ATTRIBUTES: a tag with entries there becomes "tag[attr1|attr2]", a tag
     * without any becomes the bare tag name.
     */
    public static function purifierAllowedHtml(): string
    {
        $tags = array_map(
            static fn (string $tag): string => isset(self::ALLOWED_ATTRIBUTES[$tag])
                ? sprintf('%s[%s]', $tag, implode('|', self::ALLOWED_ATTRIBUTES[$tag]))
                : $tag,
            self::ALLOWED_TAGS,
        );

        return implode(',', $tags);
    }

    /**
     * The HTMLPurifier "URI.AllowedSchemes" directive: a scheme => true map.
     *
     * @return array<string, bool>
     */
    public static function purifierAllowedSchemes(): array
    {
        return array_fill_keys(self::ALLOWED_SCHEMES, true);
    }
}
