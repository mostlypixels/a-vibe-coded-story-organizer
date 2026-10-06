<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * One theme preset, read from `config/themes.php`.
 *
 * Presets are config, not database rows: a preset's tokens, its display name and its
 * contrast ceiling and its image opacity change only when someone edits a file, so nothing about them varies
 * per request. The single runtime-varying value in the whole feature is which slug is
 * active. This value object is what ThemeStyleBlock and the Appearance picker consume,
 * so neither of them handles a raw config array.
 */
final readonly class ThemePreset
{
    /**
     * @param  array<string, string>  $tokens  token name => CSS color value
     * @param  float  $contrastCeiling  the preset's own upper bound, or the config default
     * @param  float|null  $contrastFloor  the preset's own lower bound, replacing both WCAG
     *                                     minimums; null leaves them in force
     * @param  float  $imageOpacity  opacity of content images, 0 to 1; not a color token
     */
    public function __construct(
        public string $slug,
        public string $name,
        public array $tokens,
        public float $contrastCeiling,
        public ?float $contrastFloor = null,
        public float $imageOpacity = 1.0,
    ) {}

    /**
     * @throws InvalidArgumentException when no preset is configured under that slug —
     *                                  callers that accept user input must validate the
     *                                  slug against all() first
     */
    public static function fromSlug(string $slug): self
    {
        $preset = config("themes.presets.{$slug}");

        if (! is_array($preset)) {
            throw new InvalidArgumentException("Unknown theme preset: {$slug}");
        }

        return new self(
            slug: $slug,
            name: $preset['name'] ?? $slug,
            tokens: $preset['tokens'] ?? [],
            // Absent means "no opinion beyond the house default". Read here rather
            // than in the contrast checker, so nothing downstream sees a null.
            contrastCeiling: (float) ($preset['contrast_ceiling'] ?? config('themes.contrast.default_ceiling')),
            // No default to fall back to, deliberately: absent means the WCAG floors
            // apply, and ColorContrast is where they live.
            contrastFloor: isset($preset['contrast_floor']) ? (float) $preset['contrast_floor'] : null,
            // Absent means full brightness: a missing key must never dim or break a page.
            imageOpacity: (float) ($preset['image_opacity'] ?? 1.0),
        );
    }

    /**
     * The preset a stored `theme_slug` resolves to, falling back to
     * `config('themes.default')` when the slug is `null` (never chosen) or no
     * longer matches a configured preset. The latter matters because a preset
     * can be removed from config.php after users already picked it — a stale
     * value in the column must not throw and white-screen every page.
     *
     * The single entry point from a stored slug to a preset: `<x-theme-style />`
     * and `AppearanceController` both go through it, so the fallback behaves the
     * same whether the reader is a full page or the picker marking the active
     * option.
     */
    public static function resolve(?string $slug): self
    {
        if ($slug === null || ! array_key_exists($slug, config('themes.presets', []))) {
            $slug = config('themes.default');
        }

        return self::fromSlug($slug);
    }

    /**
     * The colours a theme swatch paints. A value that fails the CSS pattern is
     * dropped, because the swatch prints it into a `style` attribute.
     *
     * @return array{stripes: list<string>, plate: ?string, plateBackground: ?string, content: ?string}
     */
    public function swatch(): array
    {
        $token = fn (string $name): ?string => preg_match(Oklch::CSS_VALUE_PATTERN, $this->tokens[$name] ?? '')
            ? $this->tokens[$name]
            : null;

        $levels = [$token('surface-raised'), $token('surface'), $token('surface-sunken')];

        return [
            'stripes' => array_values(array_filter([$token('primary'), $token('accent'), $token('focus')])),
            'plate' => $token('surface') ?? $token('surface-raised'),
            'plateBackground' => in_array(null, $levels, true)
                ? null
                : sprintf('linear-gradient(to bottom, %s 0%%, %s 50%%, %s 100%%)', ...$levels),
            'content' => $token('content'),
        ];
    }

    /**
     * Every configured preset, keyed by slug — the picker's option list.
     *
     * @return array<string, self>
     */
    public static function all(): array
    {
        $slugs = array_keys(config('themes.presets', []));

        return array_combine(
            $slugs,
            array_map(static fn (string $slug): self => self::fromSlug($slug), $slugs),
        );
    }
}
