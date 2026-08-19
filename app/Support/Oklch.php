<?php

namespace App\Support;

use InvalidArgumentException;
use Stringable;

/**
 * Represents a color with perceptual lightness, chroma, and hue.
 *
 * Conversion to sRGB reduces chroma by binary search when needed. This preserves
 * lightness and hue. WCAG luminance uses linear sRGB, not OKLCH lightness.
 */
final readonly class Oklch implements Stringable
{
    /** `#abc` or `#aabbcc`. */
    private const HEX = '\#(?:[0-9a-f]{3}|[0-9a-f]{6})';

    /** An unsigned decimal — no exponent, no sign, no leading dot. */
    private const NUMBER = '\d+(?:\.\d+)?';

    /** Matches OKLCH without alpha. Only lightness can use a percentage. */
    private const OKLCH = 'oklch\(\s*('.self::NUMBER.')(%?)\s+('.self::NUMBER.')\s+('.self::NUMBER.')\s*\)';

    /**
     * Matches every color value that theme CSS can emit.
     * The `\z` anchor rejects a trailing injected line.
     */
    public const CSS_VALUE_PATTERN = '/\A(?:'.self::HEX.'|'.self::OKLCH.')\z/i';

    /** CSS_VALUE_PATTERN's oklch half, on its own, so fromCss() can read the components. */
    private const OKLCH_PATTERN = '/\A'.self::OKLCH.'\z/i';

    /** Floating-point tolerance for an sRGB channel. */
    private const GAMUT_EPSILON = 1e-6;

    /** Binary-search depth below 8-bit channel precision. */
    private const GAMUT_SEARCH_STEPS = 24;

    public function __construct(
        public float $l,
        public float $c,
        public float $h,
    ) {}

    /**
     * Parse `#rgb`, `#rrggbb` (with or without the `#`) into OKLCH.
     *
     * @throws InvalidArgumentException when the string is not a hex color
     */
    public static function fromHex(string $hex): self
    {
        $digits = ltrim(trim($hex), '#');

        if (strlen($digits) === 3) {
            // #abc is #aabbcc.
            $digits = $digits[0].$digits[0].$digits[1].$digits[1].$digits[2].$digits[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $digits)) {
            throw new InvalidArgumentException("Not a hex color: {$hex}");
        }

        return self::fromLinearSrgb(
            self::decodeChannel(hexdec(substr($digits, 0, 2)) / 255),
            self::decodeChannel(hexdec(substr($digits, 2, 2)) / 255),
            self::decodeChannel(hexdec(substr($digits, 4, 2)) / 255),
        );
    }

    /**
     * Parses the exact hex or OKLCH syntax that theme CSS can emit.
     *
     * @throws InvalidArgumentException When the value uses neither notation.
     */
    public static function fromCss(string $value): self
    {
        if (preg_match(self::CSS_VALUE_PATTERN, $value) !== 1) {
            throw new InvalidArgumentException("Not a CSS color (expected #rrggbb or oklch(l c h)): {$value}");
        }

        if (preg_match(self::OKLCH_PATTERN, $value, $matches) === 1) {
            [, $lightness, $percent, $chroma, $hue] = $matches;

            return new self(
                $percent === '%' ? (float) $lightness / 100 : (float) $lightness,
                (float) $chroma,
                (float) $hue,
            );
        }

        return self::fromHex($value);
    }

    /**
     * The nearest sRGB hex color, as `#rrggbb`. Chroma is reduced first if needed;
     * see the gamut clipping note in the class docblock.
     */
    public function toHex(): string
    {
        [$red, $green, $blue] = $this->fittedToSrgb()->toLinearSrgb();

        return sprintf(
            '#%02x%02x%02x',
            self::encodeChannel($red),
            self::encodeChannel($green),
            self::encodeChannel($blue),
        );
    }

    /**
     * CSS form, e.g. `oklch(0.62 0.11 220)` — what a theme preset stores.
     */
    public function __toString(): string
    {
        return sprintf(
            'oklch(%s %s %s)',
            self::formatNumber($this->l, 4),
            self::formatNumber($this->c, 4),
            self::formatNumber($this->h, 2),
        );
    }

    public function withLightness(float $lightness): self
    {
        return new self($lightness, $this->c, $this->h);
    }

    public function withChroma(float $chroma): self
    {
        return new self($this->l, $chroma, $this->h);
    }

    /**
     * WCAG relative luminance (0 = black, 1 = white), used by ColorContrast.
     */
    public function relativeLuminance(): float
    {
        [$red, $green, $blue] = $this->fittedToSrgb()->toLinearSrgb();

        return 0.2126 * self::clampUnit($red)
            + 0.7152 * self::clampUnit($green)
            + 0.0722 * self::clampUnit($blue);
    }

    /**
     * This color if sRGB can show it, otherwise the same lightness and hue with the
     * chroma turned down until it fits.
     *
     * Public because a generated ramp holds one chroma across every lightness, and
     * the lightest and darkest shades of a saturated hue have no sRGB equivalent.
     * Writing the unfitted triple into a preset would store a color the browser then
     * gamut-maps by its own rules, so the value in config would not be the value on
     * screen — and the contrast figures printed beside it (which fit, via
     * relativeLuminance()) would describe a different color than the one stored.
     */
    public function fittedToSrgb(): self
    {
        $candidate = new self(self::clampUnit($this->l), max(0.0, $this->c), $this->h);

        if ($candidate->isInSrgbGamut()) {
            return $candidate;
        }

        // Binary search: `lowest` is always in gamut, `highest` never is.
        $lowest = 0.0;
        $highest = $candidate->c;

        for ($step = 0; $step < self::GAMUT_SEARCH_STEPS; $step++) {
            $middle = ($lowest + $highest) / 2;

            if ($candidate->withChroma($middle)->isInSrgbGamut()) {
                $lowest = $middle;
            } else {
                $highest = $middle;
            }
        }

        return $candidate->withChroma($lowest);
    }

    private function isInSrgbGamut(): bool
    {
        foreach ($this->toLinearSrgb() as $channel) {
            if ($channel < -self::GAMUT_EPSILON || $channel > 1 + self::GAMUT_EPSILON) {
                return false;
            }
        }

        return true;
    }

    /**
     * OKLCH → OKLab → linear sRGB. Channels may fall outside 0–1 when the color is
     * out of gamut; callers go through fittedToSrgb() first.
     *
     * @return array{float, float, float}
     */
    private function toLinearSrgb(): array
    {
        $radians = deg2rad($this->h);
        $labA = $this->c * cos($radians);
        $labB = $this->c * sin($radians);

        // The cone responses, still cube-rooted.
        $long = $this->l + 0.3963377774 * $labA + 0.2158037573 * $labB;
        $medium = $this->l - 0.1055613458 * $labA - 0.0638541728 * $labB;
        $short = $this->l - 0.0894841775 * $labA - 1.2914855480 * $labB;

        $long **= 3;
        $medium **= 3;
        $short **= 3;

        return [
            4.0767416621 * $long - 3.3077115913 * $medium + 0.2309699292 * $short,
            -1.2684380046 * $long + 2.6097574011 * $medium - 0.3413193965 * $short,
            -0.0041960863 * $long - 0.7034186147 * $medium + 1.7076147010 * $short,
        ];
    }

    /**
     * Linear sRGB → OKLab → OKLCH.
     */
    private static function fromLinearSrgb(float $red, float $green, float $blue): self
    {
        $long = self::cubeRoot(0.4122214708 * $red + 0.5363325363 * $green + 0.0514459929 * $blue);
        $medium = self::cubeRoot(0.2119034982 * $red + 0.6806995451 * $green + 0.1073969566 * $blue);
        $short = self::cubeRoot(0.0883024619 * $red + 0.2817188376 * $green + 0.6299787005 * $blue);

        $lightness = 0.2104542553 * $long + 0.7936177850 * $medium - 0.0040720468 * $short;
        $labA = 1.9779984951 * $long - 2.4285922050 * $medium + 0.4505937099 * $short;
        $labB = 0.0259040371 * $long + 0.7827717662 * $medium - 0.8086757660 * $short;

        // atan2 returns -180°–180°; theme values read better on the CSS 0°–360° dial.
        $hue = fmod(rad2deg(atan2($labB, $labA)) + 360, 360);

        return new self($lightness, sqrt($labA ** 2 + $labB ** 2), $hue);
    }

    /**
     * sRGB → linear light (the sRGB transfer function, inverted).
     */
    private static function decodeChannel(float $srgb): float
    {
        return $srgb <= 0.04045
            ? $srgb / 12.92
            : (($srgb + 0.055) / 1.055) ** 2.4;
    }

    /**
     * Linear light → an 8-bit sRGB channel.
     */
    private static function encodeChannel(float $linear): int
    {
        $linear = self::clampUnit($linear);

        $srgb = $linear <= 0.0031308
            ? 12.92 * $linear
            : 1.055 * $linear ** (1 / 2.4) - 0.055;

        return (int) round($srgb * 255);
    }

    private static function clampUnit(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    /**
     * Real cube root — `**` returns NAN for negative bases, which happens on
     * out-of-gamut inputs.
     */
    private static function cubeRoot(float $value): float
    {
        return $value < 0 ? -((-$value) ** (1 / 3)) : $value ** (1 / 3);
    }

    /**
     * Round to at most $decimals, without a trailing `.0` or `.2500`, so hues print
     * as `220` rather than `220.00`.
     */
    private static function formatNumber(float $value, int $decimals): string
    {
        $formatted = number_format($value, $decimals, '.', '');

        return str_contains($formatted, '.')
            ? rtrim(rtrim($formatted, '0'), '.')
            : $formatted;
    }
}
