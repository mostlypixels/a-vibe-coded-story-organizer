<?php

namespace Tests\Unit;

use App\Support\ThemePreset;
use App\Support\ThemeTokens;
use InvalidArgumentException;
use Tests\TestCase;

/** Guard theme configuration invariants. */
class ThemePresetTest extends TestCase
{
    public function test_every_configured_preset_defines_exactly_the_token_vocabulary(): void
    {
        foreach (ThemePreset::all() as $slug => $preset) {
            $defined = array_keys($preset->tokens);

            $this->assertSame(
                [],
                array_values(array_diff(ThemeTokens::ALL, $defined)),
                "Preset [{$slug}] is missing tokens."
            );

            $this->assertSame(
                [],
                array_values(array_diff($defined, ThemeTokens::ALL)),
                "Preset [{$slug}] defines tokens that are not in ThemeTokens::ALL."
            );
        }
    }

    public function test_all_is_keyed_by_slug(): void
    {
        $presets = ThemePreset::all();

        $this->assertSame(array_keys(config('themes.presets')), array_keys($presets));

        foreach ($presets as $slug => $preset) {
            $this->assertSame($slug, $preset->slug);
        }
    }

    public function test_the_default_preset_is_configured(): void
    {
        $this->assertArrayHasKey(config('themes.default'), ThemePreset::all());
    }

    public function test_from_slug_reads_the_configured_name_and_tokens(): void
    {
        $preset = ThemePreset::fromSlug('daylight');

        $this->assertSame('daylight', $preset->slug);
        $this->assertSame('Daylight', $preset->name);
        $this->assertSame(config('themes.presets.daylight.tokens'), $preset->tokens);
    }

    public function test_from_slug_throws_on_an_unknown_slug(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ThemePreset::fromSlug('no-such-preset');
    }

    /** Keep the compiled CSS fallback equal to the Daylight preset. */
    public function test_the_compiled_theme_block_matches_the_daylight_preset(): void
    {
        $stylesheet = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

        $this->assertSame(
            1,
            preg_match('/@theme static \{(.*?)\n\}/s', $stylesheet, $block),
            'resources/css/app.css no longer has an `@theme static` block.'
        );

        preg_match_all('/^\s*--color-([a-z-]+):\s*([^;]+);/m', $block[1], $declarations, PREG_SET_ORDER);

        $compiled = [];

        foreach ($declarations as [, $token, $value]) {
            $compiled[$token] = trim($value);
        }

        $this->assertSame(
            config('themes.presets.daylight.tokens'),
            $compiled,
            "The `@theme static` block in resources/css/app.css must hold Daylight's values, "
            .'token for token, in the same order.'
        );
    }

    public function test_every_configured_preset_sets_an_image_opacity_from_0_to_1(): void
    {
        foreach (array_keys(config('themes.presets')) as $slug) {
            $value = config("themes.presets.{$slug}.image_opacity");

            $this->assertIsNumeric($value, "Preset [{$slug}] must set image_opacity explicitly.");
            $this->assertGreaterThanOrEqual(0, $value);
            $this->assertLessThanOrEqual(1, $value);
        }
    }

    public function test_light_presets_keep_full_image_opacity_and_dark_presets_dim(): void
    {
        $this->assertSame(1.0, ThemePreset::fromSlug('daylight')->imageOpacity);
        $this->assertSame(1.0, ThemePreset::fromSlug('dusk')->imageOpacity);
        $this->assertLessThan(1.0, ThemePreset::fromSlug('low-glare-dark')->imageOpacity);
        $this->assertLessThan(1.0, ThemePreset::fromSlug('no-halation')->imageOpacity);
    }

    public function test_no_halation_dims_at_least_as_much_as_low_glare_dark(): void
    {
        $this->assertLessThanOrEqual(
            ThemePreset::fromSlug('low-glare-dark')->imageOpacity,
            ThemePreset::fromSlug('no-halation')->imageOpacity,
        );
    }

    public function test_the_image_opacity_falls_back_to_full_brightness(): void
    {
        config()->set('themes.presets.silent', ['name' => 'Silent', 'tokens' => []]);

        $this->assertSame(1.0, ThemePreset::fromSlug('silent')->imageOpacity);
    }

    public function test_the_contrast_ceiling_falls_back_to_the_config_default(): void
    {
        config()->set('themes.contrast.default_ceiling', 13.5);
        config()->set('themes.presets.opinionated', ['name' => 'Opinionated', 'tokens' => []]);

        $this->assertSame(13.5, ThemePreset::fromSlug('opinionated')->contrastCeiling);
    }

    public function test_a_preset_may_declare_its_own_contrast_ceiling(): void
    {
        config()->set('themes.presets.capped', [
            'name' => 'Capped',
            'tokens' => [],
            'contrast_ceiling' => 10.0,
        ]);

        $this->assertSame(10.0, ThemePreset::fromSlug('capped')->contrastCeiling);
    }

    /**
     * Null, not a number: the WCAG floors have no config default to fall back to, and a
     * preset that says nothing must not look like one that opted out of them.
     */
    public function test_the_contrast_floor_is_null_unless_a_preset_declares_one(): void
    {
        config()->set('themes.presets.silent', ['name' => 'Silent', 'tokens' => []]);
        config()->set('themes.presets.lowered', [
            'name' => 'Lowered',
            'tokens' => [],
            'contrast_floor' => 2.0,
        ]);

        $this->assertNull(ThemePreset::fromSlug('silent')->contrastFloor);
        $this->assertSame(2.0, ThemePreset::fromSlug('lowered')->contrastFloor);
    }

    /**
     * Every token has a chosen partner: PAIRS is what makes an unreadable combination
     * unrepresentable, and it is only that if nothing is missing from it.
     *
     * DECORATIVE tokens are exempt: `border` and `scrim` carry no contrast floor, so
     * neither has a foreground to choose. Without the exemption a token like `scrim`
     * — a background nobody writes text on — could not exist at all.
     */
    public function test_every_token_appears_in_pairs(): void
    {
        $covered = array_unique(array_merge(
            array_keys(ThemeTokens::PAIRS),
            ...array_values(ThemeTokens::PAIRS),
        ));

        $this->assertSame(
            [],
            array_values(array_diff(ThemeTokens::ALL, ThemeTokens::DECORATIVE, $covered)),
            'Every token must be a background in ThemeTokens::PAIRS or a foreground on one.'
        );

        $this->assertSame(
            [],
            array_values(array_diff($covered, ThemeTokens::ALL)),
            'ThemeTokens::PAIRS names a token that is not in ThemeTokens::ALL.'
        );
    }

    public function test_non_text_and_decorative_tokens_are_part_of_the_vocabulary(): void
    {
        $this->assertSame(
            [],
            array_values(array_diff(ThemeTokens::NON_TEXT, ThemeTokens::ALL)),
        );

        $this->assertSame(
            [],
            array_values(array_diff(ThemeTokens::DECORATIVE, ThemeTokens::ALL)),
        );
    }

    /**
     * A token exempt from every contrast floor must not also be claimed to have one —
     * the two lists answer the same question and cannot both be right about a token.
     */
    public function test_a_token_is_never_both_non_text_and_decorative(): void
    {
        $this->assertSame(
            [],
            array_values(array_intersect(ThemeTokens::NON_TEXT, ThemeTokens::DECORATIVE)),
        );
    }

    public function test_swatch_returns_three_stripes_for_a_complete_preset(): void
    {
        $swatch = ThemePreset::fromSlug('daylight')->swatch();

        $this->assertCount(3, $swatch['stripes']);
        $this->assertNotNull($swatch['plate']);
        $this->assertNotNull($swatch['plateBackground']);
        $this->assertNotNull($swatch['content']);
    }

    public function test_swatch_drops_a_value_that_fails_the_css_pattern(): void
    {
        $tokens = ThemePreset::fromSlug('daylight')->tokens;
        $tokens['primary'] = 'red; background: url(x)';
        $tokens['content'] = '</style>';
        $tokens['surface-sunken'] = 'nope';

        $swatch = (new ThemePreset('bad', 'Bad', $tokens, 7.0))->swatch();

        $this->assertSame([$tokens['accent'], $tokens['focus']], $swatch['stripes']);
        $this->assertNull($swatch['content']);
        $this->assertNull($swatch['plateBackground']);
        $this->assertSame($tokens['surface'], $swatch['plate']);
    }
}
