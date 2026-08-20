<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** Guard word-count labels and variants. */
class WordCountComponentTest extends TestCase
{
    /** Render a component and reject literal `<x-…>` output. */
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

    public function test_a_negative_count_is_plural(): void
    {
        // A challenge whose writer cut more than they added shows a negative
        // total. No range in the translation key matches a negative number, so
        // the plural branch is chosen on the size of the count.
        $rendered = $this->render('<x-word-count :count="$count" />', ['count' => -2300]);

        $this->assertStringContainsString('-2,300 words', $rendered);
        $this->assertStringNotContainsString('-2,300 word ', $rendered);
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
