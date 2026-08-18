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
 * Defines rich-HTML fields and the sanitizer allow-list.
 *
 * The allow-list must include all HTML that the editor produces. Scene contents
 * are Markdown and do not belong in this registry.
 */
class RichTextFields
{
    /** @var array<class-string, list<string>> */
    public const FIELDS = [
        Project::class => ['description'],
        Act::class => ['description'],
        Chapter::class => ['description'],
        Plotline::class => ['description'],
        Event::class => ['description'],
        Scene::class => ['description', 'notes'],
        CodexEntry::class => ['description'],
    ];

    /** @var list<string> Tags that the editor can produce and the sanitizer permits. */
    public const ALLOWED_TAGS = [
        'p', 'h1', 'h2', 'h3', 'h4', 'strong', 'em', 'u', 's', 'sub', 'sup',
        'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'a', 'br', 'hr',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'img',
        'label', 'input', 'span', 'div',
    ];

    /**
     * HTMLPurifier limits attribute names but not their values.
     *
     * @var array<string, list<string>>
     */
    public const ALLOWED_ATTRIBUTES = [
        'a' => ['href'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        // TipTap uses these attributes for task lists.
        'ul' => ['data-type'],
        'li' => ['data-type', 'data-checked'],
        // GFM adds `disabled` to its static task-list checkboxes.
        'input' => ['type', 'checked', 'disabled'],
        // Merged cells need these structural attributes. The editor removes table styles.
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
        // Callouts use a blockquote with a data-callout attribute.
        'blockquote' => ['data-callout-type'],
    ];

    /** @var list<string> Relative URLs remain valid without an entry here. */
    public const ALLOWED_SCHEMES = ['http', 'https'];

    /** @return list<string> Rich-HTML fields as "Model.field" strings. */
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
     * @param  class-string  $model
     * @return list<string>
     */
    public static function forModel(string $model): array
    {
        return self::FIELDS[$model] ?? [];
    }

    /** @param class-string $model */
    public static function isRich(string $model, string $field): bool
    {
        return in_array($field, self::forModel($model), true);
    }

    /** Builds the HTMLPurifier allow-list directive. */
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

    /** @return array<string, bool> */
    public static function purifierAllowedSchemes(): array
    {
        return array_fill_keys(self::ALLOWED_SCHEMES, true);
    }
}
