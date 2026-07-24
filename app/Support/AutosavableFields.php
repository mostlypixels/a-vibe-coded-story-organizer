<?php

namespace App\Support;

use App\Enums\FieldKind;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Rules\SanitizeHtml;
use App\Rules\ValidMarkdown;

/**
 * Single source of truth for which model+field pairs the autosave-with-revisions
 * feature covers, keyed by the URL slug the feature's routes accept (mirrors the
 * app's own URL segments — `codex`, not `codex-entry`, per handoff.md §9.3).
 *
 * This is the only place `FieldAutosaveController` (task 6) resolves a slug to a
 * model class, and the only place its validation rules come from — the autosave
 * endpoint and the existing Form Requests must never validate the same field two
 * different ways (handoff.md §9.8). An unregistered slug never reaches the
 * controller at all: `routes/web.php` gates the `{entity}` segment with
 * `->whereIn('entity', AutosavableFields::slugs())`, so it 404s at the router.
 *
 * Sits beside RichTextFields (same directory, same "single source of truth"
 * pattern as PlotlineColors/CodexMediaRules) and *references* it for the rich
 * subset rather than absorbing it — RichTextFields stays scoped to the rich-HTML
 * feature; this class only labels which of its fields are also autosavable (in
 * this feature, every rich field happens to be autosavable, but the two lists are
 * conceptually independent and must not be merged).
 */
class AutosavableFields
{
    /**
     * type slug => [model class, [field => FieldKind, ...]].
     *
     * This is `handoff.md` §7 / `expanded/architecture.md`'s 14-field table,
     * reshaped by slug for URL resolution — copied verbatim, never add or drop a
     * field here without updating that table first.
     *
     * @var array<string, array{0: class-string, 1: array<string, FieldKind>}>
     */
    public const REGISTRY = [
        'project' => [Project::class, [
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

    /**
     * Every registered URL slug, e.g. for `->whereIn('entity', ...)` route gating.
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_keys(self::REGISTRY);
    }

    /**
     * The model class registered for a slug.
     *
     * @return class-string
     */
    public static function modelFor(string $slug): string
    {
        return self::REGISTRY[$slug][0];
    }

    /**
     * The reverse of {@see self::modelFor()}: the URL slug a given model class is
     * registered under. Used by App\Services\RevisionRecorder, which only ever has
     * a Model instance (never the slug it was reached through) and still needs to
     * look up its coalescing window / character cap via the slug-keyed config
     * readers below.
     *
     * @param  class-string  $modelClass
     */
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

    /**
     * How long a run of autosaves to this field keeps overwriting the same open
     * revision row before the next save opens a new one (RevisionRecorder's
     * coalescing window). Reads config('revisions.windows'), keyed "Model.field"
     * with a "default" fallback — never hard-code a per-field number here.
     */
    public static function windowSeconds(string $slug, string $field): int
    {
        return self::configuredValue('windows', $slug, $field);
    }

    /**
     * The maximum character length allowed for this field. Reads
     * config('revisions.caps'), keyed "Model.field" with a "default" fallback —
     * the same source validationRule() uses, so the autosave endpoint and the
     * existing Form Requests can never drift.
     */
    public static function characterCap(string $slug, string $field): int
    {
        return self::configuredValue('caps', $slug, $field);
    }

    /**
     * The validation rule array for this field, built from its FieldKind and
     * character cap. Delegates to the same rule objects the existing Form
     * Requests use (SanitizeHtml, ValidMarkdown) so the two paths can never
     * validate the same field two different ways (handoff.md §9.8).
     *
     * @return array<int, mixed>
     */
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
     * Look up a "Model.field" keyed config value (config/revisions.php's
     * 'windows'/'caps' arrays), falling back to that array's 'default' entry.
     *
     * Deliberately not a single `config('revisions.windows.Model.field')` dotted
     * call: Laravel's config() helper splits on every dot, which would treat
     * "Model.field" as two nested array levels instead of the single literal
     * string key config/revisions.php actually uses.
     */
    private static function configuredValue(string $configKey, string $slug, string $field): int
    {
        $values = config("revisions.{$configKey}");

        $lookupKey = class_basename(self::modelFor($slug)).'.'.$field;

        return $values[$lookupKey] ?? $values['default'];
    }
}
