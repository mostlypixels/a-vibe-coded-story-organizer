<?php

namespace Tests\Unit\Services;

use App\Services\Diff\DiffHtmlRenderer;
use App\Services\Diff\HtmlTokenizer;
use App\Services\Diff\VisualHtmlDiffer;
use Tests\TestCase;

/**
 * App\Services\Diff\DiffHtmlRenderer: structure in, safe HTML out.
 *
 * Half of these are security tests, and they are the point of the class: the
 * renderer is the *only* producer of `<ins>`/`<del>`, and its output is
 * displayed with `{!! !!}` — so "no stored value can contribute markup" has to
 * be proven, not assumed.
 */
class DiffHtmlRendererTest extends TestCase
{
    private VisualHtmlDiffer $differ;

    private DiffHtmlRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->differ = new VisualHtmlDiffer(new HtmlTokenizer);
        $this->renderer = new DiffHtmlRenderer;
    }

    private function render(?string $old, ?string $new, bool $inline = false): string
    {
        return $this->renderer->render($this->differ->diff($old, $new)->blocks, $inline);
    }

    /** Every tag name appearing in a rendered result. @return list<string> */
    private function tagsIn(string $html): array
    {
        preg_match_all('/<\s*\/?\s*([a-zA-Z0-9]+)/', $html, $matches);

        return array_values(array_unique($matches[1]));
    }

    // ---------------------------------------------------------------------
    // Markers
    // ---------------------------------------------------------------------

    public function test_a_changed_word_is_wrapped_in_del_and_ins(): void
    {
        $html = $this->render('<p>The cat sat</p>', '<p>The dog sat</p>');

        $this->assertStringContainsString('<del class="diff-del">', $html);
        $this->assertStringContainsString('cat', $html);
        $this->assertStringContainsString('<ins class="diff-ins">', $html);
        $this->assertStringContainsString('dog', $html);

        // The unchanged words are not inside a marker.
        $this->assertMatchesRegularExpression('/<p[^>]*>The /', $html);
    }

    public function test_every_marker_carries_a_visually_hidden_label(): void
    {
        // Colour alone must never be the only signal that something changed.
        $html = $this->render('<p>The cat sat</p>', '<p>The dog sat</p>');

        $this->assertStringContainsString('<span class="sr-only">inserted </span>', $html);
        $this->assertStringContainsString('<span class="sr-only">removed </span>', $html);
    }

    public function test_a_formatting_change_names_the_marks_that_moved(): void
    {
        $html = $this->render('<p>The cat sat</p>', '<p>The <strong>cat</strong> sat</p>');

        $this->assertStringContainsString('class="diff-formatting-changed"', $html);
        $this->assertStringContainsString('data-marks-added="strong"', $html);
    }

    public function test_the_authors_own_formatting_survives_in_full_mode(): void
    {
        $html = $this->render('<p>The <strong>cat</strong> sat</p>', '<p>The <strong>cat</strong> ran</p>');

        $this->assertStringContainsString('<strong>cat</strong>', $html);
    }

    public function test_a_list_is_rebuilt_with_its_wrapper(): void
    {
        // A bare <li> is invalid HTML that browsers discard — taking the diff
        // with it.
        $html = $this->render('<ol><li>One</li></ol>', '<ol><li>One</li><li>Two</li></ol>');

        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('</ol>', $html);
        $this->assertSame(2, substr_count($html, '<li'));
    }

    public function test_a_table_row_is_rebuilt_with_its_cells(): void
    {
        $html = $this->render(
            '<table><tbody><tr><td>Melusine</td><td>Witch</td></tr></tbody></table>',
            '<table><tbody><tr><td>Melusine</td><td>Sorceress</td></tr></tbody></table>',
        );

        $this->assertStringContainsString('<table><tbody>', $html);
        $this->assertStringContainsString('<tr', $html);
        $this->assertStringContainsString('Sorceress', $html);
    }

    // ---------------------------------------------------------------------
    // Security
    // ---------------------------------------------------------------------

    public function test_a_stored_del_tag_cannot_produce_a_change_marker(): void
    {
        // The sanitizer strips <del> on write, so this cannot arrive through the
        // editor — but it can arrive through an import, and the renderer has to
        // hold on its own.
        $html = $this->render('<p>Before</p>', '<p>Before <del>injected</del></p>');

        // The word survives as text (losing content would be its own bug); the
        // tag does not, because the tokenizer only ever hands the renderer words
        // and marks, and `del` is not a mark.
        $this->assertStringContainsString('injected', $html);

        preg_match_all('/<del[^>]*>/', $html, $matches);

        foreach ($matches[0] as $marker) {
            $this->assertSame('<del class="diff-del">', $marker, 'A stored value forged a change marker.');
        }
    }

    public function test_script_tags_and_special_characters_in_content_are_escaped(): void
    {
        $html = $this->render('<p>Safe</p>', '<p>&lt;script&gt;alert("x")&lt;/script&gt; &amp; "quotes"</p>');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&quot;', $html);
    }

    public function test_the_output_contains_no_tag_outside_the_renderers_allow_list(): void
    {
        $html = $this->render(
            '<p>One</p><ul><li>Item</li></ul>',
            '<h2>One</h2><ul><li>Item changed</li></ul><blockquote data-callout-type="note"><p>Quote</p></blockquote>'
            .'<pre><code>code</code></pre><hr><p><img src="https://example.test/a.png" alt="pic"></p>',
        );

        foreach ($this->tagsIn($html) as $tag) {
            $this->assertContains($tag, DiffHtmlRenderer::EMITTED_TAGS, "Unexpected tag <{$tag}> in diff output.");
        }
    }

    public function test_a_javascript_link_is_emitted_without_its_href(): void
    {
        $html = $this->render(
            '<p>Click</p>',
            '<p>Click <a href="javascript:alert(1)">here</a></p>',
        );

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('<a>here</a>', $html);
    }

    public function test_an_http_link_keeps_its_href(): void
    {
        $html = $this->render('<p>Click</p>', '<p>Click <a href="https://example.test">here</a></p>');

        $this->assertStringContainsString('<a href="https://example.test">here</a>', $html);
    }

    // ---------------------------------------------------------------------
    // Summary (inline) mode
    // ---------------------------------------------------------------------

    public function test_inline_mode_drops_the_authors_formatting_but_keeps_the_markers(): void
    {
        $html = $this->render(
            '<p>The <strong>cat</strong> sat</p>',
            '<p>The <strong>dog</strong> sat</p>',
            inline: true,
        );

        // A history row is a scan target: bold competing with the markers is
        // noise.
        $this->assertStringNotContainsString('<strong>', $html);
        $this->assertStringNotContainsString('<p', $html);
        $this->assertStringContainsString('<del class="diff-del">', $html);
        $this->assertStringContainsString('<ins class="diff-ins">', $html);
    }

    public function test_inline_mode_escapes_content_too(): void
    {
        $html = $this->render('<p>Safe</p>', '<p>&lt;del&gt;fake&lt;/del&gt;</p>', inline: true);

        $this->assertStringContainsString('&lt;del&gt;', $html);
        $this->assertStringNotContainsString('<del>fake', $html);
    }
}
