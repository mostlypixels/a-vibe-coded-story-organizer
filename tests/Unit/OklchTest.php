<?php

namespace Tests\Unit;

use App\Support\Oklch;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OklchTest extends TestCase
{
    /**
     * Hex colors spread across the gamut: the extremes, a near-white, and saturated
     * anchors from several hue families.
     *
     * @return array<string, array{string}>
     */
    public static function hexColorProvider(): array
    {
        return [
            'white' => ['#ffffff'],
            'black' => ['#000000'],
            'mid gray' => ['#808080'],
            'near white' => ['#f8fafc'],
            'red' => ['#ef4444'],
            'teal' => ['#0f766e'],
            'blue' => ['#219ebc'],
            'indigo' => ['#6366f1'],
            'yellow' => ['#eab308'],
        ];
    }

    #[DataProvider('hexColorProvider')]
    public function test_it_round_trips_hex_through_oklch(string $hex): void
    {
        $this->assertSame($hex, Oklch::fromHex($hex)->toHex());
    }

    public function test_it_accepts_shorthand_and_unprefixed_hex(): void
    {
        $this->assertSame('#aabbcc', Oklch::fromHex('#abc')->toHex());
        $this->assertSame('#219ebc', Oklch::fromHex('219ebc')->toHex());
    }

    public function test_it_rejects_a_string_that_is_not_a_hex_color(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Oklch::fromHex('cornflower');
    }

    /**
     * The presets are written in both notations, so anything reading config has to go
     * through fromCss() rather than assuming hex — the trap that made most of Daylight
     * unreadable to ColorContrast.
     */
    #[DataProvider('hexColorProvider')]
    public function test_from_css_round_trips_hex(string $hex): void
    {
        $this->assertSame($hex, Oklch::fromCss($hex)->toHex());
    }

    public function test_from_css_round_trips_oklch_notation(): void
    {
        $this->assertSame('oklch(0.62 0.11 220)', (string) Oklch::fromCss('oklch(0.62 0.11 220)'));

        // Whitespace inside the function, and case, are CSS's business.
        $this->assertSame('oklch(0.62 0.11 220)', (string) Oklch::fromCss('OKLCH( 0.62  0.11  220 )'));
    }

    /**
     * Tailwind 4's own palette is written with a percentage lightness, so every gray
     * token Daylight inherits arrives in this form.
     */
    public function test_from_css_reads_a_percentage_lightness_as_a_fraction(): void
    {
        $gray = Oklch::fromCss('oklch(96.7% 0.003 264.542)');

        $this->assertEqualsWithDelta(0.967, $gray->l, 0.0001);
        $this->assertEqualsWithDelta(0.003, $gray->c, 0.0001);
        $this->assertEqualsWithDelta(264.542, $gray->h, 0.0001);

        // And the two spellings of the same colour agree.
        $this->assertSame((string) Oklch::fromCss('oklch(0.967 0.003 264.542)'), (string) $gray);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function junkColorProvider(): array
    {
        return [
            'a colour name' => ['cornflower'],
            'empty' => [''],
            'oklch with too few components' => ['oklch(0.62 0.11)'],
            'oklch with an alpha channel' => ['oklch(0.62 0.11 220 / 0.5)'],
            'oklch unclosed' => ['oklch(0.62 0.11 220'],
            'a css function we do not store' => ['rgb(1 2 3)'],
            'a variable reference' => ['var(--color-navy-950)'],
            'a value with a smuggled declaration' => ['#fff;color:red'],
            'a value with a trailing newline' => ["#ffffff\n"],
            'hex without the hash' => ['219ebc'],
        ];
    }

    #[DataProvider('junkColorProvider')]
    public function test_from_css_rejects_anything_else(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        Oklch::fromCss($value);
    }

    /**
     * The renderer's whitelist and this parser must accept the same set: a value that
     * reaches the page but cannot be measured is a value with no contrast guarantee.
     */
    public function test_the_css_value_pattern_accepts_exactly_what_from_css_parses(): void
    {
        foreach (['#fff', '#ffffff', 'oklch(0.62 0.11 220)', 'oklch(96.7% 0.003 264.542)'] as $accepted) {
            $this->assertSame(1, preg_match(Oklch::CSS_VALUE_PATTERN, $accepted), $accepted);
        }

        foreach (self::junkColorProvider() as [$rejected]) {
            $this->assertSame(0, preg_match(Oklch::CSS_VALUE_PATTERN, $rejected), $rejected);
        }
    }

    public function test_relative_luminance_matches_wcag_figures_at_the_extremes(): void
    {
        $this->assertEqualsWithDelta(1.0, Oklch::fromHex('#ffffff')->relativeLuminance(), 0.0001);
        $this->assertEqualsWithDelta(0.0, Oklch::fromHex('#000000')->relativeLuminance(), 0.0001);
    }

    /**
     * The extremes agree with OKLCH lightness, so they cannot catch luminance being
     * approximated with `l`. These two midtones can: mid gray sits at `l` ≈ 0.60 but
     * WCAG luminance 0.216, and #767676 at `l` ≈ 0.56 against luminance 0.181.
     *
     * Both grays are published WCAG reference values — #767676 is the lightest gray
     * that passes 4.5:1 on white, #595959 the lightest that passes 7:1.
     */
    public function test_relative_luminance_matches_wcag_figures_in_the_midtones(): void
    {
        $midGray = Oklch::fromHex('#808080');
        $passingGray = Oklch::fromHex('#767676');

        $this->assertEqualsWithDelta(0.2159, $midGray->relativeLuminance(), 0.0005);
        $this->assertEqualsWithDelta(0.1812, $passingGray->relativeLuminance(), 0.0005);

        // The trap this test exists for: lightness is a different quantity entirely.
        $this->assertGreaterThan(0.55, $midGray->l);
    }

    public function test_out_of_gamut_chroma_is_clamped_without_shifting_the_hue(): void
    {
        // No sRGB color is this saturated at this lightness.
        $impossible = new Oklch(0.6, 0.4, 220);

        $rendered = Oklch::fromHex($impossible->toHex());

        $this->assertEqualsWithDelta(220, $rendered->h, 1.5);
        $this->assertEqualsWithDelta(0.6, $rendered->l, 0.02);
        $this->assertLessThan(0.4, $rendered->c);
    }

    public function test_out_of_range_lightness_clamps_to_black_and_white(): void
    {
        $this->assertSame('#ffffff', (new Oklch(1.4, 0.1, 220))->toHex());
        $this->assertSame('#000000', (new Oklch(-0.3, 0.1, 220))->toHex());
    }

    public function test_it_renders_as_a_css_oklch_function(): void
    {
        $this->assertSame('oklch(0.62 0.11 220)', (string) new Oklch(0.62, 0.11, 220));
        $this->assertSame('oklch(0 0 0)', (string) new Oklch(0, 0, 0));
    }

    public function test_immutable_steps_return_new_instances(): void
    {
        $base = new Oklch(0.62, 0.11, 220);

        $lighter = $base->withLightness(0.9);
        $duller = $base->withChroma(0.02);

        $this->assertSame(0.62, $base->l, 'The original is untouched.');
        $this->assertSame(0.11, $base->c);

        $this->assertSame(0.9, $lighter->l);
        $this->assertSame(220.0, $lighter->h);
        $this->assertSame(0.02, $duller->c);
        $this->assertSame(0.62, $duller->l);
    }
}
