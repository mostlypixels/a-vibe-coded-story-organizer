<?php

namespace App\Services;

use App\Support\Oklch;
use App\Support\ThemePreset;
use App\Support\ThemeTokens;

/**
 * Renders a preset as the body of the `<style>` block in every layout's head — one
 * unlayered `:root` rule that repaints the whole app without a rebuild.
 *
 * ## Why the rule wins
 *
 * Tailwind compiles `@theme` into `@layer theme { :root { … } }`. An **unlayered**
 * `:root` rule outranks any cascade layer regardless of source order (CSS Cascade 5),
 * which is the entire mechanism. Placing the block after the stylesheet is convention,
 * not the reason. The fragile direction is the opposite one: wrap this output in
 * `@layer` anywhere and it silently loses.
 *
 * ## Why it validates
 *
 * The component prints this with `{!! !!}` — unescaped, because escaped CSS is not CSS.
 * That alone is the reason every value is whitelisted against a strict hex / `oklch()`
 * pattern: a value carrying `</style>`, `url(javascript:…)` or a stray `;` would
 * otherwise reach the page verbatim. Values that fail are dropped, so the token falls
 * back to the compiled `@theme` default — a visible wrong color, never broken markup.
 *
 * The pattern itself is `Oklch::CSS_VALUE_PATTERN`, so "a value this renderer will
 * print" and "a value Oklch can parse" are one definition rather than two that drift:
 * a preset value the contrast matrix measures is exactly a value that reaches the page.
 *
 * ## No cache
 *
 * The default cache store is `database`, so caching would trade ~40 `sprintf` calls for
 * a SQL round-trip on every page render. Ramps are not recomputed per request either
 * way: preset values are stored, not derived.
 */
final class ThemeStyleBlock
{
    public function render(ThemePreset $preset): string
    {
        $declarations = '';

        foreach ($this->declarations($preset) as $property => $value) {
            $declarations .= sprintf('%s:%s;', $property, $value);
        }

        return ":root{{$declarations}}";
    }

    /**
     * The same custom properties {@see render()} prints, as an array — the form the
     * Appearance picker sends to the live preview.
     *
     * The picker gets them from here and not from a second walk of the config, so a
     * previewed theme and a saved theme cannot paint different pixels, and the
     * whitelist above guards both.
     *
     * @return array<string, string> `--color-<token>` => CSS color value
     */
    public function declarations(ThemePreset $preset): array
    {
        $declarations = [];

        // Iterate ALL, not the preset's array: token order stays stable in devtools and
        // an unknown key in config cannot reach the page.
        foreach (ThemeTokens::ALL as $token) {
            $value = $preset->tokens[$token] ?? null;

            if (! is_string($value) || preg_match(Oklch::CSS_VALUE_PATTERN, $value) !== 1) {
                continue;
            }

            $declarations["--color-{$token}"] = $value;
        }

        return $declarations;
    }
}
