<?php

namespace Tests\Feature;

use App\Enums\Verdict;
use App\Support\ColorContrast;
use App\Support\ThemePreset;
use App\Support\ThemeTokens;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The guard that makes the token vocabulary worth having: every foreground a preset
 * may paint on a background is measured against that background, in every preset.
 *
 * `ThemeTokens::PAIRS` decides what "may" means, so a token added without a partner
 * is caught by ThemePresetTest, and a partner chosen badly is caught here. Between
 * them there is no way to ship a combination nobody looked at.
 *
 * ## Floors reject, and so does the ceiling — here
 *
 * The floors are WCAG minimums and global. The ceiling is per preset and is documented
 * as "warn, don't reject" — but that governs *user* input in a later spec, where a
 * person picking their own colours should not be refused. These presets are ours, and
 * PHPUnit has no warning level, so a preset breaching the ceiling it declared for
 * itself is asserted as the bug it is.
 *
 * ## Why a raw `require` instead of `config()`
 *
 * PHPUnit calls a data provider before `setUp()`, so the application is not booted and
 * `config()` would fail. The provider reads `config/themes.php` off disk; the test body
 * then resolves the preset through `ThemePreset::fromSlug()`, so the ceiling still comes
 * from the same code path the application uses.
 */
class ThemeContrastTest extends TestCase
{
    /**
     * Preset slug + one background/foreground pair, for every combination of the two.
     *
     * @return iterable<string, array{string, string, string}>
     */
    public static function tokenPairProvider(): iterable
    {
        foreach (array_keys(self::presets()) as $slug) {
            foreach (ThemeTokens::PAIRS as $background => $foregrounds) {
                foreach ($foregrounds as $foreground) {
                    // A decorative token has no floor at either end of the pair, so
                    // there is nothing here to assert about it. See DECORATIVE.
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
            ColorContrast::verdict($ratio, $isText, $preset->contrastCeiling),
            sprintf(
                'Preset [%s]: `%s` on `%s` measures %.2f:1, outside the %s–%.1f band.',
                $slug,
                $foreground,
                $background,
                $ratio,
                $isText ? ColorContrast::TEXT_FLOOR : ColorContrast::NON_TEXT_FLOOR,
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

    /**
     * Two surfaces sharing a value is silent: nothing errors, a card simply disappears
     * into the page behind it. Only a second theme makes it visible, which is why this
     * is asserted on the generated presets.
     *
     * Daylight is excluded deliberately, not forgotten — `x-card`, `x-table`,
     * `x-dropdown` and `x-modal`'s panel are all white in the original design, so
     * `surface-raised` and `surface-overlay` are the same colour. Separating them is
     * the "regularize Daylight" follow-up, not a contrast fix.
     */
    public function test_the_generated_presets_use_four_distinct_surfaces(): void
    {
        $surfaces = ['surface', 'surface-raised', 'surface-sunken', 'surface-overlay'];

        foreach (['dusk', 'low-glare-dark'] as $slug) {
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
