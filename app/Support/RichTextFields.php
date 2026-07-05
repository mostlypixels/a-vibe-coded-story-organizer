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
 * Str::markdown()), never routed through the sanitizer or the editor.
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
     * <script>/<iframe>/<object>, no <img> (image upload is a v2 concern), and no
     * presentational attributes. Kept in sync with the editor slash menu (task 04).
     *
     * @var list<string>
     */
    public const ALLOWED_TAGS = [
        'p', 'h1', 'h2', 'h3', 'h4', 'strong', 'em', 'u', 's',
        'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'a', 'br', 'hr',
    ];

    /**
     * URL schemes allowed on <a href>. Relative URLs carry no scheme and remain
     * permitted; javascript: and data: are blocked by omission.
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
     * The HTMLPurifier "HTML.Allowed" directive built from the tag allow-list.
     * Only <a> carries an attribute (href); everything else is bare.
     */
    public static function purifierAllowedHtml(): string
    {
        $tags = array_map(
            static fn (string $tag): string => $tag === 'a' ? 'a[href]' : $tag,
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
