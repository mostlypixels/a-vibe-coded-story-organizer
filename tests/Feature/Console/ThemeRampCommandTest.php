<?php

namespace Tests\Feature\Console;

use App\Support\Oklch;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Asserts the *properties* of a ramp, never a particular palette.
 *
 * The command exists so a human can choose colours, so pinning its output to a literal
 * list of values would only record today's taste and break the moment the curve is
 * retuned. What must not break is what makes a generated ramp better than an eyeballed
 * one: eleven shades, evenly spaced in perceived lightness, all of the same hue.
 *
 * No database is touched — the command reads config and prints.
 */
class ThemeRampCommandTest extends TestCase
{
    /** How far apart two consecutive lightness steps may drift before the ramp is uneven. */
    private const EVENNESS_TOLERANCE = 0.005;

    public function test_it_prints_eleven_shades_keyed_50_to_950(): void
    {
        $this->assertSame(
            [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950],
            array_keys($this->ramp(['anchor' => '#219ebc'])),
        );
    }

    public function test_lightness_falls_strictly_from_the_lightest_shade_to_the_darkest(): void
    {
        $lightnesses = array_map(
            static fn (Oklch $shade): float => $shade->l,
            array_values($this->ramp(['anchor' => '#219ebc'])),
        );

        foreach (array_slice($lightnesses, 1) as $index => $lightness) {
            $this->assertLessThan(
                $lightnesses[$index],
                $lightness,
                'Each shade must be darker than the one before it.'
            );
        }
    }

    /**
     * The property the app's five hand-authored sRGB ramps never had: in OKLCH an equal
     * step of lightness is an equal step of *perceived* lightness, so a ramp whose steps
     * are equal looks evenly spaced. Uneven steps are what make a hand-picked ramp jump.
     */
    public function test_consecutive_lightness_steps_are_even(): void
    {
        $lightnesses = array_map(
            static fn (Oklch $shade): float => $shade->l,
            array_values($this->ramp(['anchor' => '#219ebc'])),
        );

        $steps = [];

        foreach (array_slice($lightnesses, 1) as $index => $lightness) {
            $steps[] = $lightnesses[$index] - $lightness;
        }

        foreach ($steps as $step) {
            $this->assertEqualsWithDelta($steps[0], $step, self::EVENNESS_TOLERANCE);
        }
    }

    /**
     * A ramp is one colour at eleven lightnesses. A hue that drifts across the ramp is
     * the classic sRGB failure — a dark blue that turns purple — and the reason the
     * gamut fit reduces chroma rather than clamping channels.
     */
    public function test_the_anchor_hue_is_held_across_the_ramp(): void
    {
        $anchorHue = Oklch::fromCss('#219ebc')->h;

        foreach ($this->ramp(['anchor' => '#219ebc']) as $shade => $color) {
            $this->assertEqualsWithDelta($anchorHue, $color->h, 0.01, "Shade {$shade} drifted off the anchor hue.");
        }
    }

    public function test_max_chroma_caps_every_shade(): void
    {
        $ramp = $this->ramp(['anchor' => '#219ebc', '--max-chroma' => '0.05']);

        foreach ($ramp as $shade => $color) {
            $this->assertLessThanOrEqual(0.05, $color->c, "Shade {$shade} is over the chroma cap.");
        }
    }

    /**
     * The cap only ever reduces: an anchor already below it produces the same ramp as
     * no cap at all, so `--max-chroma` cannot quietly desaturate a palette that did not
     * need it.
     */
    public function test_a_cap_above_the_anchor_chroma_leaves_the_ramp_untouched(): void
    {
        $anchorChroma = Oklch::fromCss('#219ebc')->c;

        $this->assertEquals(
            $this->ramp(['anchor' => '#219ebc']),
            $this->ramp(['anchor' => '#219ebc', '--max-chroma' => (string) ($anchorChroma + 0.1)]),
        );
    }

    /**
     * The anchor and the colours measured against it both come out of
     * `config/themes.php`, where most values are `oklch()` rather than hex.
     */
    public function test_it_accepts_both_notations_for_the_anchor_and_the_comparisons(): void
    {
        $this->artisan('theme:ramp', [
            'anchor' => 'oklch(96.7% 0.003 264.542)',
            '--against' => ['#ffffff', 'oklch(0.232 0.018 250)'],
        ])->assertSuccessful();
    }

    public function test_it_prints_a_contrast_verdict_beside_every_shade(): void
    {
        $verdicts = $this->verdicts(['anchor' => '#219ebc', '--against' => ['#ffffff']]);

        $this->assertCount(11, $verdicts);

        foreach ($verdicts as $shade => $verdict) {
            $this->assertContains($verdict, ['too_low', 'ok', 'too_high'], "Shade {$shade} has no verdict.");
        }
    }

    /**
     * Borders and focus rings are judged against 3:1, not 4.5:1, so the flag can only
     * ever move a shade towards passing.
     */
    public function test_the_non_text_flag_lowers_the_floor(): void
    {
        $asText = $this->verdicts(['anchor' => '#219ebc', '--against' => ['#ffffff']]);
        $asNonText = $this->verdicts(['anchor' => '#219ebc', '--against' => ['#ffffff'], '--non-text' => true]);

        foreach ($asText as $shade => $verdict) {
            if ($verdict !== 'too_low') {
                $this->assertNotSame('too_low', $asNonText[$shade], "Shade {$shade} got worse under a lower floor.");
            }
        }

        $this->assertNotSame($asText, $asNonText, 'Some shade should sit between the two floors.');
    }

    public function test_it_fails_on_an_anchor_it_cannot_parse(): void
    {
        $this->artisan('theme:ramp', ['anchor' => 'cornflower'])->assertFailed();
    }

    public function test_it_fails_on_a_comparison_colour_it_cannot_parse(): void
    {
        $this->artisan('theme:ramp', ['anchor' => '#219ebc', '--against' => ['puce']])->assertFailed();
    }

    public function test_it_rejects_a_chroma_cap_of_zero_or_less(): void
    {
        $this->artisan('theme:ramp', ['anchor' => '#219ebc', '--max-chroma' => '0'])->assertFailed();
    }

    /**
     * The command's output is its interface — it is read and pasted by hand — so the
     * tests read it the same way the author does.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<int, Oklch> shade key => the colour printed for it
     */
    private function ramp(array $parameters): array
    {
        preg_match_all(
            '/^\|\s*(\d+)\s*\|\s*(oklch\([^)]*\))\s*\|/m',
            $this->rampOutput($parameters),
            $rows,
            PREG_SET_ORDER,
        );

        $ramp = [];

        foreach ($rows as [, $shade, $value]) {
            $ramp[(int) $shade] = Oklch::fromCss($value);
        }

        return $ramp;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<int, string> shade key => the verdict in the first comparison column
     */
    private function verdicts(array $parameters): array
    {
        preg_match_all(
            '/^\|\s*(\d+)\s*\|\s*oklch\([^)]*\)\s*\|\s*\d+\.\d{2}\s+(too_low|ok|too_high)\s*\|/m',
            $this->rampOutput($parameters),
            $rows,
            PREG_SET_ORDER,
        );

        $verdicts = [];

        foreach ($rows as [, $shade, $verdict]) {
            $verdicts[(int) $shade] = $verdict;
        }

        return $verdicts;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function rampOutput(array $parameters): string
    {
        $this->assertSame(0, Artisan::call('theme:ramp', $parameters), 'theme:ramp failed.');

        return Artisan::output();
    }
}
