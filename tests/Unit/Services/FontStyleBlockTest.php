<?php

namespace Tests\Unit\Services;

use App\Services\FontStyleBlock;
use App\Support\FontChoice;
use Tests\TestCase;

/**
 * Unlike ThemeStyleBlock, this renderer prints only config-authored values —
 * FontChoice::resolve() guarantees that, so there is nothing to whitelist here.
 * These tests cover the fallback behaviour that guarantee depends on, and the
 * shape of the emitted rule.
 */
class FontStyleBlockTest extends TestCase
{
    public function test_a_null_field_renders_the_config_default(): void
    {
        $css = (new FontStyleBlock)->render(FontChoice::resolve(null, null, null, null, null));

        $this->assertStringContainsString(
            '--font-sans:'.config('fonts.families.'.config('fonts.default_ui').'.stack').';',
            $css,
        );
        $this->assertStringContainsString(
            '--font-manuscript:'.config('fonts.families.'.config('fonts.default_manuscript').'.stack').';',
            $css,
        );
        $this->assertStringContainsString(
            'font-size:'.config('fonts.ui_scales.'.config('fonts.default_ui_scale')).';',
            $css,
        );
        $this->assertStringContainsString(
            '--manuscript-scale:'.config('fonts.manuscript_scales.'.config('fonts.default_manuscript_scale')).';',
            $css,
        );
        $this->assertStringContainsString(
            '--manuscript-leading:'.FontChoice::lineHeightsFor('manuscript')[config('fonts.default_leading')].';',
            $css,
        );
        $this->assertStringContainsString(
            '--tw-leading:'.FontChoice::lineHeightsFor('ui')[config('fonts.default_ui_leading')].';',
            $css,
        );
    }

    public function test_a_slug_removed_from_config_renders_the_default_instead_of_throwing(): void
    {
        $choice = FontChoice::resolve('no-such-family', 'no-such-family', 'no-such-scale', 'no-such-scale', 'no-such-leading');

        $css = (new FontStyleBlock)->render($choice);

        $this->assertStringContainsString(
            '--font-sans:'.config('fonts.families.'.config('fonts.default_ui').'.stack').';',
            $css,
        );
    }

    /**
     * An unknown slug never reaches FontChoice::resolve()'s output in the first
     * place, but this asserts the renderer itself never leaks one through either.
     */
    public function test_an_unknown_scale_or_leading_slug_never_reaches_the_output(): void
    {
        $choice = FontChoice::resolve(null, null, 'no-such-scale', 'no-such-scale', 'no-such-leading');

        $css = (new FontStyleBlock)->render($choice);

        $this->assertStringNotContainsString('no-such-scale', $css);
        $this->assertStringNotContainsString('no-such-leading', $css);
    }

    public function test_the_rule_is_unlayered_and_starts_with_root(): void
    {
        $css = (new FontStyleBlock)->render(FontChoice::resolve(null, null, null, null, null));

        $this->assertStringStartsWith(':root{', $css);
        $this->assertStringNotContainsString('@layer', $css);
        $this->assertSame(1, substr_count($css, '{'));
        $this->assertSame(1, substr_count($css, '}'));
    }

    public function test_it_renders_a_chosen_family_stack(): void
    {
        $choice = FontChoice::resolve('atkinson', 'literata', 'large', 'larger', 'airy');

        $css = (new FontStyleBlock)->render($choice);

        $this->assertStringContainsString('--font-sans:'.config('fonts.families.atkinson.stack').';', $css);
        $this->assertStringContainsString('--font-manuscript:'.config('fonts.families.literata.stack').';', $css);
        $this->assertStringContainsString('font-size:'.config('fonts.ui_scales.large').';', $css);
        $this->assertStringContainsString('--manuscript-scale:'.config('fonts.manuscript_scales.larger').';', $css);
        $this->assertStringContainsString('--manuscript-leading:'.FontChoice::lineHeightsFor('manuscript')['airy'].';', $css);
    }

    /**
     * `--tw-leading` is the slot every Tailwind `text-*` utility reads its
     * line-height from, so it is how the interface leading reaches the chrome.
     */
    public function test_the_interface_leading_renders_as_the_tailwind_leading_variable(): void
    {
        $css = (new FontStyleBlock)->render(
            FontChoice::resolve(null, null, null, null, 'airy', 'roomier')
        );

        $this->assertStringContainsString('--tw-leading:'.FontChoice::lineHeightsFor('ui')['roomier'].';', $css);
        $this->assertStringContainsString('--manuscript-leading:'.FontChoice::lineHeightsFor('manuscript')['airy'].';', $css);
    }

    public function test_an_unconfigured_interface_leading_falls_back_to_the_default(): void
    {
        $css = (new FontStyleBlock)->render(
            FontChoice::resolve(null, null, null, null, null, 'gone-from-config')
        );

        $this->assertStringContainsString('--tw-leading:'.FontChoice::lineHeightsFor('ui')[config('fonts.default_ui_leading')].';', $css);
        $this->assertStringNotContainsString('gone-from-config', $css);
    }
}
