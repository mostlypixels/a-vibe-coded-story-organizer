<?php

namespace Tests\Unit;

use App\Enums\SearchMode;
use App\Support\SearchSnippet;
use PHPUnit\Framework\TestCase;

class SearchSnippetTest extends TestCase
{
    public function test_it_truncates_a_long_string_around_the_match(): void
    {
        $filler = str_repeat('lorem ipsum dolor sit amet ', 40); // ~1080 chars
        $text = $filler.'dragon'.$filler;

        $snippet = SearchSnippet::highlight($text, 'dragon');

        // The snippet is a small window, not the whole ~2000-char string.
        $this->assertLessThan(mb_strlen($text), mb_strlen(strip_tags($snippet)));
        $this->assertLessThanOrEqual(
            SearchSnippet::CONTEXT_LENGTH + 10,
            mb_strlen(strip_tags(str_replace("\u{2026}", '', $snippet)))
        );
        // Context on both sides means leading/trailing ellipsis.
        $this->assertStringContainsString("\u{2026}", $snippet);
    }

    public function test_it_wraps_the_matched_term_in_a_mark_element(): void
    {
        $snippet = SearchSnippet::highlight('The castle stood tall.', 'castle');

        $this->assertStringContainsString('<mark class="bg-sun-200">castle</mark>', $snippet);
    }

    public function test_matching_is_case_insensitive(): void
    {
        $snippet = SearchSnippet::highlight('A fearsome dragon appeared.', 'Dragon');

        // The original casing of the source text is preserved inside the mark.
        $this->assertStringContainsString('<mark class="bg-sun-200">dragon</mark>', $snippet);
    }

    public function test_it_escapes_html_special_characters_in_surrounding_text(): void
    {
        $text = 'Rock & roll <b>bold</b> and a dragon nearby.';

        $snippet = SearchSnippet::highlight($text, 'dragon');

        $this->assertStringContainsString('Rock &amp; roll', $snippet);
        $this->assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $snippet);
        // No raw, unescaped bold tag survives.
        $this->assertStringNotContainsString('<b>bold</b>', $snippet);
    }

    public function test_it_does_not_reproduce_script_tags_as_executable_markup(): void
    {
        $text = 'Before <script>alert(1)</script> the dragon roared.';

        $snippet = SearchSnippet::highlight($text, 'dragon');

        // The only real tag in the output is our <mark> wrapper.
        $this->assertStringNotContainsString('<script>', $snippet);
        $this->assertStringContainsString('&lt;script&gt;', $snippet);
        $this->assertStringContainsString('<mark class="bg-sun-200">dragon</mark>', $snippet);
    }

    public function test_it_highlights_multiple_terms_from_an_array(): void
    {
        $text = 'The dragon guarded the castle on the hill.';

        $snippet = SearchSnippet::highlight($text, ['dragon', 'castle']);

        $this->assertStringContainsString('<mark class="bg-sun-200">dragon</mark>', $snippet);
        $this->assertStringContainsString('<mark class="bg-sun-200">castle</mark>', $snippet);
    }

    public function test_it_returns_an_escaped_excerpt_when_no_term_matches(): void
    {
        $text = 'Rock & roll all night.';

        $snippet = SearchSnippet::highlight($text, 'dragon');

        $this->assertStringContainsString('Rock &amp; roll', $snippet);
        $this->assertStringNotContainsString('<mark', $snippet);
    }

    public function test_search_mode_has_exactly_three_cases_each_with_a_label(): void
    {
        $cases = SearchMode::cases();

        $this->assertCount(3, $cases);
        $this->assertSame(
            ['all', 'any', 'exact'],
            array_map(fn (SearchMode $mode) => $mode->value, $cases)
        );

        foreach ($cases as $mode) {
            $this->assertNotEmpty($mode->label());
        }
    }
}
