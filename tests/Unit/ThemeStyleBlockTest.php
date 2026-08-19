<?php

namespace Tests\Unit;

use App\Services\ThemeStyleBlock;
use App\Support\ThemePreset;
use App\Support\ThemeTokens;
use Tests\TestCase;

/** Reject unsafe values before theme CSS reaches unescaped output. */
class ThemeStyleBlockTest extends TestCase
{
    public function test_it_renders_one_root_rule_with_every_token(): void
    {
        $css = (new ThemeStyleBlock)->render($this->preset(
            array_fill_keys(ThemeTokens::ALL, '#ffffff')
        ));

        $this->assertSame(1, substr_count($css, ':root'));
        $this->assertSame(1, substr_count($css, '{'));

        foreach (ThemeTokens::ALL as $token) {
            $this->assertStringContainsString("--color-{$token}:#ffffff;", $css);
        }
    }

    public function test_it_accepts_both_hex_and_oklch_notation(): void
    {
        $css = (new ThemeStyleBlock)->render($this->preset([
            'surface' => '#FFF',
            'content' => 'oklch(0.62 0.11 220)',
            'primary' => 'oklch(96.7% 0.003 264.542)',
        ]));

        $this->assertStringContainsString('--color-surface:#FFF;', $css);
        $this->assertStringContainsString('--color-content:oklch(0.62 0.11 220);', $css);
        $this->assertStringContainsString('--color-primary:oklch(96.7% 0.003 264.542);', $css);
    }

    /**
     * A hostile value is dropped rather than sanitised, so the token falls back to
     * the compiled @theme default — a wrong colour, never broken markup.
     */
    public function test_it_drops_hostile_values_and_still_renders_a_valid_rule(): void
    {
        $css = (new ThemeStyleBlock)->render($this->preset([
            'surface' => '#fff',
            'content' => '</style><script>alert(1)</script>',
            'primary' => 'url(javascript:alert(1))',
            'link' => 'expression(alert(1))',
            'focus' => 'red;color:red',
            'border' => "#fff\n</style>",
            'accent' => '#fff}body{display:none',
            'nav' => 'var(--color-navy-950)',
        ]));

        $this->assertStringNotContainsString('<', $css);
        $this->assertStringNotContainsString('script', $css);
        $this->assertStringNotContainsString('javascript', $css);
        $this->assertStringNotContainsString('expression', $css);
        $this->assertStringNotContainsString('var(', $css);

        // One opening and one closing brace: nothing escaped the rule.
        $this->assertSame(1, substr_count($css, '{'));
        $this->assertSame(1, substr_count($css, '}'));

        // The one good value survives; every hostile sibling is gone.
        $this->assertStringContainsString('--color-surface:#fff;', $css);
        foreach (['content', 'primary', 'link', 'focus', 'border', 'accent', 'nav'] as $dropped) {
            $this->assertStringNotContainsString("--color-{$dropped}:", $css);
        }
    }

    /**
     * An unknown key in a preset cannot reach the page: the renderer walks
     * ThemeTokens::ALL, never the preset's own array.
     */
    public function test_it_ignores_tokens_that_are_not_in_the_vocabulary(): void
    {
        $css = (new ThemeStyleBlock)->render($this->preset([
            'surface' => '#fff',
            'not-a-token' => '#000',
        ]));

        $this->assertStringNotContainsString('not-a-token', $css);
    }

    /**
     * The guard that a shipped preset's values are all renderable. A value that
     * fails validation is silently dropped at runtime, so nothing else would notice.
     */
    public function test_every_configured_preset_renders_all_of_its_tokens(): void
    {
        foreach (ThemePreset::all() as $slug => $preset) {
            $css = (new ThemeStyleBlock)->render($preset);

            foreach (ThemeTokens::ALL as $token) {
                $this->assertStringContainsString(
                    "--color-{$token}:",
                    $css,
                    "Preset [{$slug}] has no renderable value for token [{$token}]."
                );
            }
        }
    }

    /**
     * The array the Appearance live preview consumes carries exactly what the rule
     * prints, and drops the same values: a preview cannot show a colour the saved
     * page refuses.
     */
    public function test_declarations_hold_the_same_properties_the_rule_prints(): void
    {
        $preset = $this->preset([
            'surface' => '#fff',
            'content' => 'oklch(0.62 0.11 220)',
            'primary' => '</style><script>alert(1)</script>',
        ]);

        $block = new ThemeStyleBlock;
        $css = $block->render($preset);

        $this->assertSame([
            '--color-surface' => '#fff',
            '--color-content' => 'oklch(0.62 0.11 220)',
        ], $block->declarations($preset));

        foreach ($block->declarations($preset) as $property => $value) {
            $this->assertStringContainsString("{$property}:{$value};", $css);
        }
    }

    /**
     * @param  array<string, string>  $tokens
     */
    private function preset(array $tokens): ThemePreset
    {
        return new ThemePreset('fixture', 'Fixture', $tokens, 15.0);
    }
}
