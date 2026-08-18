<?php

namespace App\Support;

use App\Enums\FieldKind;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Rules\SanitizeHtml;
use App\Rules\ValidMarkdown;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps autosave route slugs to models, fields, and validation rules.
 *
 * Rich-text fields remain in {@see RichTextFields}. The two registries serve
 * different features and must remain independent.
 */
class AutosavableFields
{
    /**
     * > [!WARNING]
     * > Add each field's database size and revision cap with its registry entry.
     *
     * Codex attribute values use story-time history and must not use edit-time history.
     *
     * @var array<string, array{0: class-string, 1: array<string, FieldKind>}>
     */
    public const REGISTRY = [
        'project' => [Project::class, [
            'description' => FieldKind::Rich,
        ]],
        'book' => [Book::class, [
            'description' => FieldKind::Rich,
            'dedication' => FieldKind::Markdown,
            'acknowledgements' => FieldKind::Markdown,
            'preface' => FieldKind::Markdown,
            'postface' => FieldKind::Markdown,
            'rights' => FieldKind::Plain,
        ]],
        'act' => [Act::class, [
            'description' => FieldKind::Rich,
        ]],
        'chapter' => [Chapter::class, [
            'description' => FieldKind::Rich,
        ]],
        'plotline' => [Plotline::class, [
            'description' => FieldKind::Rich,
        ]],
        'event' => [Event::class, [
            'description' => FieldKind::Rich,
        ]],
        'scene' => [Scene::class, [
            'description' => FieldKind::Rich,
            'notes' => FieldKind::Rich,
            'contents' => FieldKind::Markdown,
        ]],
        'codex' => [CodexEntry::class, [
            'description' => FieldKind::Rich,
        ]],
    ];

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::REGISTRY);
    }

    /** @return class-string */
    public static function modelFor(string $slug): string
    {
        return self::REGISTRY[$slug][0];
    }

    /**
     * Rejects unknown fields. The router has already validated the slug.
     *
     * @return array{0: class-string, 1: array<string, FieldKind>}
     */
    public static function resolveField(string $slug, string $field): array
    {
        [$modelClass, $fields] = self::REGISTRY[$slug];

        abort_unless(array_key_exists($field, $fields), 404);

        return [$modelClass, $fields];
    }

    /** @param class-string $modelClass */
    public static function slugFor(string $modelClass): string
    {
        foreach (self::REGISTRY as $slug => [$class, $fields]) {
            if ($class === $modelClass) {
                return $slug;
            }
        }

        throw new \InvalidArgumentException("No autosave slug registered for model [{$modelClass}].");
    }

    /**
     * The FieldKind registered for a slug+field pair.
     */
    public static function kindOf(string $slug, string $field): FieldKind
    {
        return self::REGISTRY[$slug][1][$field];
    }

    /** Returns the revision coalescing window for a field. */
    public static function windowSeconds(string $slug, string $field): int
    {
        return self::configuredValue('windows', $slug, $field);
    }

    /** Returns the configured character limit for a field. */
    public static function characterCap(string $slug, string $field): int
    {
        return self::configuredValue('caps', $slug, $field);
    }

    /** @return array<int, mixed> Rules shared with the full-save requests. */
    public static function validationRule(string $slug, string $field): array
    {
        $cap = self::characterCap($slug, $field);

        return match (self::kindOf($slug, $field)) {
            FieldKind::Rich => ['nullable', 'string', "max:{$cap}", new SanitizeHtml],
            FieldKind::Markdown => ['nullable', 'string', "max:{$cap}", new ValidMarkdown],
            FieldKind::Plain => ['nullable', 'string', "max:{$cap}"],
        };
    }

    /**
     * Captures submitted fields before the model changes.
     *
     * @return array<string, string>
     */
    public static function snapshotFieldsBeforeUpdate(Model $model, array $data): array
    {
        $fields = self::REGISTRY[self::slugFor($model::class)][1];

        $snapshot = [];

        foreach (array_keys($fields) as $field) {
            if (array_key_exists($field, $data)) {
                $snapshot[$field] = (string) ($model->getAttribute($field) ?? '');
            }
        }

        return $snapshot;
    }

    /**
     * Reads a literal "entity.field" key with a default fallback.
     *
     * Laravel's dotted config lookup would split this literal key.
     */
    private static function configuredValue(string $configKey, string $slug, string $field): int
    {
        $values = config("revisions.{$configKey}");

        return $values["{$slug}.{$field}"] ?? $values['default'];
    }
}
