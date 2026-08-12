<?php

namespace Tests\Unit;

use App\Support\ThemePreset;
use App\Support\ThemeTokens;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Presets are config, so the invariants a model's `saving` hook would have guarded are
 * guarded here instead — cheaper and earlier.
 *
 * Extends Tests\TestCase, unlike its plain-PHPUnit neighbours, because every assertion
 * reads `config('themes.*')`. No database is touched.
 */
class ThemePresetTest extends TestCase
{
    /**
     * A missing key renders no custom property at all, so the token silently keeps the
     * previous theme's colour; an unknown key is a typo that never reaches the page and
     * would otherwise look like it worked.
     */
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

    /**
     * `resources/css/app.css`'s `@theme static` block is the compiled fallback every
     * utility resolves against before `<x-theme-style />` overrides it, and it holds
     * Daylight's values.
     *
     * Until the hue ramps were deleted those values were `var()` references to them,
     * so there was exactly one copy of each colour. Deleting the ramps duplicated
     * them, and this is what stops the copies drifting: change a Daylight colour in
     * `config/themes.php` without mirroring it into the stylesheet and every page
     * still renders correctly (the runtime block wins) right up until someone loads a
     * page before the CSS-in-HTML block applies, or reads the stylesheet to learn what
     * the app's colours are. A silent divergence, so it gets a loud test.
     */
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
}
