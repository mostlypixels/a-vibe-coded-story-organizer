<?php

namespace Tests\Unit\Support;

use App\Support\CanonicalPunctuation;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Asserts the PHP implementation against `tests/Fixtures/punctuation.json`, the
 * same file {@see PunctuationFixtureTest} checks against SmartPunct.
 */
class CanonicalPunctuationTest extends TestCase
{
    #[DataProvider('fixtureCases')]
    public function test_it_matches_the_fixture(string $input, string $expected): void
    {
        $this->assertSame($expected, CanonicalPunctuation::inPlainText($input));
    }

    #[DataProvider('fixtureCases')]
    public function test_it_is_idempotent(string $input, string $expected): void
    {
        $this->assertSame($expected, CanonicalPunctuation::inPlainText($expected));
    }

    public function test_markdown_normalizes_prose(): void
    {
        $this->assertSame(
            "a – b\n\nWait…\n",
            CanonicalPunctuation::inMarkdown("a -- b\n\nWait...\n"),
        );
    }

    public function test_markdown_leaves_fenced_blocks_alone(): void
    {
        $markdown = "a -- b\n\n```php\n\$x = 'a -- b'; // Wait...\n```\n\n~~~\na -- b\n~~~\n\nWait...\n";

        $this->assertSame(
            "a – b\n\n```php\n\$x = 'a -- b'; // Wait...\n```\n\n~~~\na -- b\n~~~\n\nWait…\n",
            CanonicalPunctuation::inMarkdown($markdown),
        );
    }

    public function test_markdown_leaves_indented_code_blocks_alone(): void
    {
        $markdown = "a -- b\n\n    a -- b\n    Wait...\n\nWait...\n";

        $this->assertSame(
            "a – b\n\n    a -- b\n    Wait...\n\nWait…\n",
            CanonicalPunctuation::inMarkdown($markdown),
        );
    }

    public function test_markdown_leaves_backtick_code_spans_alone(): void
    {
        $this->assertSame(
            'Use `a -- b` for a – b, and ``a `--` b`` too: Wait…',
            CanonicalPunctuation::inMarkdown('Use `a -- b` for a -- b, and ``a `--` b`` too: Wait...'),
        );
    }

    public function test_markdown_is_idempotent(): void
    {
        $markdown = "a -- b\n\n```\na -- b\n```\n\nUse `a -- b`.\n";
        $once = CanonicalPunctuation::inMarkdown($markdown);

        $this->assertSame($once, CanonicalPunctuation::inMarkdown($once));
    }

    public function test_html_normalizes_text_nodes(): void
    {
        $this->assertSame(
            '<p>a – b <strong>Wait…</strong></p>',
            CanonicalPunctuation::inHtml('<p>a -- b <strong>Wait...</strong></p>'),
        );
    }

    public function test_html_leaves_pre_and_code_alone(): void
    {
        $html = '<p>a -- b</p><pre><code>a -- b</code></pre><p>Wait... <code>a -- b</code></p>';

        $this->assertSame(
            '<p>a – b</p><pre><code>a -- b</code></pre><p>Wait… <code>a -- b</code></p>',
            CanonicalPunctuation::inHtml($html),
        );
    }

    public function test_html_leaves_attribute_values_alone(): void
    {
        $this->assertSame(
            '<a href="https://example.com/a--b" title="a -- b">a – b</a>',
            CanonicalPunctuation::inHtml('<a href="https://example.com/a--b" title="a -- b">a -- b</a>'),
        );
    }

    public function test_html_is_idempotent(): void
    {
        $html = '<p>"Hello," she said -- and left...</p><code>a -- b</code>';
        $once = CanonicalPunctuation::inHtml($html);

        $this->assertSame($once, CanonicalPunctuation::inHtml($once));
    }

    public static function fixtureCases(): array
    {
        $cases = json_decode(
            file_get_contents(__DIR__.'/../../Fixtures/punctuation.json'),
            true,
        );

        $named = [];
        foreach ($cases as $case) {
            $named[$case['input']] = [$case['input'], $case['expected']];
        }

        return $named;
    }
}
