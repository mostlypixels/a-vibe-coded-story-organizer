<?php

namespace Tests\Feature;

use App\Enums\Verdict;
use App\Support\ColorContrast;
use App\Support\ThemePreset;
use App\Support\ThemeTokens;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Check every declared background and foreground pair in each preset. */
class ThemeContrastTest extends TestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function tokenPairProvider(): iterable
    {
        foreach (array_keys(self::presets()) as $slug) {
            foreach (ThemeTokens::PAIRS as $background => $foregrounds) {
                foreach ($foregrounds as $foreground) {
                    // Decorative tokens have no contrast floor.
                    if (in_array($background, ThemeTokens::DECORATIVE, true)
                        || in_array($foreground, ThemeTokens::DECORATIVE, true)) {
                        continue;
                    }

                    yield "{$slug}: {$foreground} on {$background}" => [$slug, $background, $foreground];
                }
            }
        }
    }

    #[DataProvider('tokenPairProvider')]
    public function test_every_token_pair_sits_inside_its_presets_contrast_band(
        string $slug,
        string $background,
        string $foreground,
    ): void {
        $preset = ThemePreset::fromSlug($slug);

        // `accent`, `border-strong` and `focus` are shapes rather than glyphs — a ring,
        // a divider, an indicator — so WCAG holds them to 3:1, not 4.5:1.
        $isText = ! in_array($foreground, ThemeTokens::NON_TEXT, true);

        $ratio = ColorContrast::ratio(
            $preset->tokens[$foreground],
            $preset->tokens[$background],
        );

        $this->assertSame(
            Verdict::Ok,
            ColorContrast::verdict($ratio, $isText, $preset->contrastCeiling, $preset->contrastFloor),
            sprintf(
                'Preset [%s]: `%s` on `%s` measures %.2f:1, outside the %s–%.1f band.',
                $slug,
                $foreground,
                $background,
                $ratio,
                $preset->contrastFloor ?? ($isText ? ColorContrast::TEXT_FLOOR : ColorContrast::NON_TEXT_FLOOR),
                $preset->contrastCeiling,
            ),
        );
    }

    /**
     * A provider that quietly yields nothing turns this whole file green, so pin the
     * shape of what it produced: every configured preset, every non-decorative pair.
     */
    public function test_the_matrix_covers_every_preset(): void
    {
        $slugs = [];

        foreach (self::tokenPairProvider() as [$slug]) {
            $slugs[$slug] = true;
        }

        $this->assertSame(array_keys(config('themes.presets')), array_keys($slugs));
        $this->assertGreaterThan(40, iterator_count(self::tokenPairProvider()) / count($slugs));
    }

    /** Require distinct elevations in presets designed with four surfaces. */
    public function test_the_generated_presets_use_four_distinct_surfaces(): void
    {
        $surfaces = ['surface', 'surface-raised', 'surface-sunken', 'surface-overlay'];

        foreach (['dusk', 'low-glare-dark', 'no-halation'] as $slug) {
            $values = array_map(
                static fn (string $token): string => ThemePreset::fromSlug($slug)->tokens[$token],
                $surfaces,
            );

            $this->assertCount(
                4,
                array_unique($values),
                "Preset [{$slug}] collapses two of its four surfaces onto one value."
            );
        }
    }

    /**
     * Dropping below the WCAG minimums is a decision taken once, for readers the
     * minimums make worse off. A second preset doing it is far more likely to be
     * someone silencing a failing assertion than a second such decision, so the list
     * is pinned rather than left to grow.
     */
    public function test_only_no_halation_leaves_the_wcag_floors(): void
    {
        $overriding = array_keys(array_filter(
            ThemePreset::all(),
            static fn (ThemePreset $preset): bool => $preset->contrastFloor !== null,
        ));

        $this->assertSame(['no-halation'], $overriding);
        $this->assertSame(2.0, ThemePreset::fromSlug('no-halation')->contrastFloor);
    }

    /**
     * The exemption has to stay visible. If DECORATIVE ever empties out, the provider
     * above silently starts asserting floors nobody chose to apply — and if it grows,
     * that is a decision someone should have to read this test to make.
     */
    public function test_the_skip_list_is_explicit(): void
    {
        $this->assertSame(['border', 'scrim'], ThemeTokens::DECORATIVE);
    }

    /**
     * @return array<string, array{name: string, tokens: array<string, string>}>
     */
    private static function presets(): array
    {
        /** @var array{presets: array<string, array{name: string, tokens: array<string, string>}>} $themes */
        $themes = require dirname(__DIR__, 2).'/config/themes.php';

        return $themes['presets'];
    }
}
