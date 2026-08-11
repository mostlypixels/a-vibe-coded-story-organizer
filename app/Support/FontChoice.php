<?php

namespace App\Support;

/**
 * The five stored font-appearance slugs, resolved into the CSS values
 * `config/fonts.php` authors for them.
 *
 * Mirrors ThemePreset: every field falls back to its `config('fonts.default_*')`
 * value when the stored slug is `null` (never chosen) or no longer configured,
 * so a family or scale removed from config.php never throws — it just stops
 * being reachable. `resolve()` is the only entry point from stored columns to
 * CSS; FontStyleBlock and the appearance picker both go through it.
 */
final readonly class FontChoice
{
    public function __construct(
        public string $uiSlug,
        public string $uiStack,
        public string $manuscriptSlug,
        public string $manuscriptStack,
        public string $uiScaleSlug,
        public string $uiScale,
        public string $manuscriptScaleSlug,
        public string $manuscriptScale,
        public string $leadingSlug,
        public string $leading,
        public string $uiLeadingSlug,
        public string $uiLeading,
    ) {}

    /**
     * `leading` holds a multiplier of the surface's default line height, which
     * CSS cannot apply on its own — a rule cannot read the line height it
     * overrides. These turn the multiplier into the value to render.
     */
    public function manuscriptLineHeight(): string
    {
        return self::lineHeight($this->leading, 'manuscript');
    }

    public function uiLineHeight(): string
    {
        return self::lineHeight($this->uiLeading, 'ui');
    }

    /**
     * The list the live preview needs: the same slugs, already multiplied for
     * one surface, because the browser must never do this arithmetic on a value
     * it read out of the form.
     *
     * @return array<string, string>
     */
    public static function lineHeightsFor(string $surface): array
    {
        return array_map(
            static fn (string $multiplier): string => self::lineHeight($multiplier, $surface),
            config('fonts.leading', []),
        );
    }

    private static function lineHeight(string $multiplier, string $surface): string
    {
        $base = (float) config("fonts.leading_bases.{$surface}", 1.5);

        return (string) round($base * (float) $multiplier, 4);
    }

    public static function resolve(
        ?string $ui,
        ?string $manuscript,
        ?string $uiScale,
        ?string $manuscriptScale,
        ?string $leading,
        ?string $uiLeading = null,
    ): self {
        $families = config('fonts.families', []);
        $uiScales = config('fonts.ui_scales', []);
        $manuscriptScales = config('fonts.manuscript_scales', []);
        $leadings = config('fonts.leading', []);

        $uiSlug = self::withinOrDefault($ui, $families, config('fonts.default_ui'));
        $manuscriptSlug = self::withinOrDefault($manuscript, $families, config('fonts.default_manuscript'));
        $uiScaleSlug = self::withinOrDefault($uiScale, $uiScales, config('fonts.default_ui_scale'));
        $manuscriptScaleSlug = self::withinOrDefault($manuscriptScale, $manuscriptScales, config('fonts.default_manuscript_scale'));
        $leadingSlug = self::withinOrDefault($leading, $leadings, config('fonts.default_leading'));
        $uiLeadingSlug = self::withinOrDefault($uiLeading, $leadings, config('fonts.default_ui_leading'));

        return new self(
            uiSlug: $uiSlug,
            uiStack: $families[$uiSlug]['stack'],
            manuscriptSlug: $manuscriptSlug,
            manuscriptStack: $families[$manuscriptSlug]['stack'],
            uiScaleSlug: $uiScaleSlug,
            uiScale: $uiScales[$uiScaleSlug],
            manuscriptScaleSlug: $manuscriptScaleSlug,
            manuscriptScale: $manuscriptScales[$manuscriptScaleSlug],
            leadingSlug: $leadingSlug,
            leading: $leadings[$leadingSlug],
            uiLeadingSlug: $uiLeadingSlug,
            uiLeading: $leadings[$uiLeadingSlug],
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function withinOrDefault(?string $slug, array $options, string $default): string
    {
        return $slug !== null && array_key_exists($slug, $options) ? $slug : $default;
    }
}
