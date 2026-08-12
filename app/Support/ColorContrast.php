<?php

namespace App\Support;

use App\Enums\Verdict;

/**
 * Measures the WCAG contrast ratio between two colors and judges it.
 *
 * Used when *authoring* a theme preset — by the ramp command and by the tests guarding
 * the presets' token pairs — never while rendering a page.
 *
 * ## Floors reject, the ceiling only warns
 *
 * The floors are the WCAG 2.x minimums, so they are fixed and the same for every theme:
 * 4.5:1 for text, 3:1 for non-text UI (borders, focus rings, accents). The **ceiling**
 * is the opposite kind of number — a taste judgement that differs per preset (a
 * low-glare dark theme caps contrast to avoid halation; a low-vision theme would want
 * it higher), so it is always passed in, never a constant here.
 *
 * ## A preset may replace the floors, and only deliberately
 *
 * `verdict()` takes an optional floor that stands in for both WCAG minimums. It exists
 * for a preset built for readers whom the minimums make *worse* off — severe halation,
 * where the bloom around a glyph is the thing that stops it being read — and it is
 * the whole of the escape hatch: one number, per preset, written down in
 * `config/themes.php` where a reviewer sees it. See the `no-halation` preset there.
 */
final class ColorContrast
{
    /** WCAG 2.x minimum for body text and any text-on-background pair. */
    public const TEXT_FLOOR = 4.5;

    /** WCAG 2.x minimum for non-text UI: borders, focus rings, accent marks. */
    public const NON_TEXT_FLOOR = 3.0;

    /**
     * The WCAG contrast ratio between two colors, from 1.0 (identical) to 21.0
     * (black on white). Order does not matter. Strings are accepted so callers can
     * compare raw config values without converting first — in either notation a
     * preset may store, since most of Daylight is `oklch(…)` rather than hex.
     */
    public static function ratio(Oklch|string $first, Oklch|string $second): float
    {
        $firstLuminance = self::resolve($first)->relativeLuminance();
        $secondLuminance = self::resolve($second)->relativeLuminance();

        $lighter = max($firstLuminance, $secondLuminance);
        $darker = min($firstLuminance, $secondLuminance);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Judge a ratio against the floor for its kind and the ceiling it is handed.
     *
     * @param  bool  $isText  true for a text-on-background pair (4.5 floor), false for
     *                        non-text UI such as borders and focus rings (3.0 floor)
     * @param  float  $ceiling  the acting preset's upper bound; a ceiling below the
     *                          applicable floor is raised to it, so that no ratio can
     *                          be both too low and too high
     * @param  float|null  $floor  a preset's own floor, replacing *both* WCAG minimums;
     *                             null keeps them
     */
    public static function verdict(float $ratio, bool $isText, float $ceiling, ?float $floor = null): Verdict
    {
        $floor ??= $isText ? self::TEXT_FLOOR : self::NON_TEXT_FLOOR;

        if ($ratio < $floor) {
            return Verdict::TooLow;
        }

        return $ratio > max($ceiling, $floor) ? Verdict::TooHigh : Verdict::Ok;
    }

    private static function resolve(Oklch|string $color): Oklch
    {
        return $color instanceof Oklch ? $color : Oklch::fromCss($color);
    }
}
