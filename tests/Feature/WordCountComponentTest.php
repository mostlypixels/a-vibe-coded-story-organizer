<?php

namespace Tests\Feature;

use App\Support\WordCounter;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * `<x-word-count>` — the one place a word count is formatted.
 *
 * Rendered standalone via `Blade::render()`, in the manner of
 * {@see IconButtonComponentTest}. The index and story tests assert the rendered
 * string on a real page; these cover zero, the singular, and the two variants,
 * which those pages never reach.
 *
 * The component takes a plain int, never a model — {@see WordCounter} on the
 * write side and this component on the read side agree only on that integer.
 */
class WordCountComponentTest extends TestCase
{
    /**
     * Render a template, asserting the component tag actually compiled — see
     * {@see IconButtonComponentTest::render()} for why an uncompiled tag would let every
     * other assertion here pass against literal source text.
     */
    private function render(string $template, array $data = []): string
    {
        $rendered = Blade::render($template, $data);

        $this->assertStringNotContainsString(
            '<x-',
            $rendered,
            'A component tag was emitted as literal text instead of being compiled. '
            .'The usual cause is a Blade directive (@disabled, @if) used as an attribute '
            .'inside an <x-…> tag — use a bound attribute (:disabled="…") instead.',
        );

        return $rendered;
    }

    public function test_zero_renders_as_zero_words_not_blank_or_a_dash(): void
    {
        $rendered = $this->render('<x-word-count :count="$count" />', ['count' => 0]);

        $this->assertStringContainsString('0 words', $rendered);
        // The tag's own class names contain dashes ("text-gray-400"), so assert on the
        // rendered *content* between the tags rather than the whole string — a lone "-"
        // or "—" placeholder there is exactly what this test exists to rule out.
        $this->assertMatchesRegularExpression('/>\s*0 words\s*</', $rendered);
    }

    public function test_one_is_singular(): void
    {
        $rendered = $this->render('<x-word-count :count="$count" />', ['count' => 1]);

        $this->assertStringContainsString('1 word', $rendered);
        // Not "1 words" — the singular branch must actually be reachable rather than the
        // plural branch matching "1 word" as a substring of "1 words".
        $this->assertStringNotContainsString('1 words', $rendered);
    }

    public function test_large_counts_are_thousands_separated(): void
    {
        $rendered = $this->render('<x-word-count :count="$count" />', ['count' => 1234]);

        $this->assertStringContainsString('1,234 words', $rendered);
        $this->assertStringNotContainsString('1234', $rendered);
    }

    public function test_the_muted_variant_is_the_default_and_carries_its_classes(): void
    {
        $rendered = $this->render('<x-word-count :count="$count" />', ['count' => 42]);

        $this->assertStringContainsString('text-xs text-content-subtle', $rendered);
        $this->assertStringContainsString('42 words', $rendered);
    }

    public function test_the_inline_variant_carries_no_muted_classes(): void
    {
        $rendered = $this->render('<x-word-count :count="$count" variant="inline" />', ['count' => 42]);

        $this->assertStringNotContainsString('text-gray-400', $rendered);
        $this->assertStringContainsString('42 words', $rendered);
    }
}
