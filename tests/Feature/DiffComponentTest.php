<?php

namespace Tests\Feature;

use App\Enums\FieldKind;
use App\Services\RevisionDiffer;
use App\Services\RevisionSummarizer;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Task 12 — the `<x-diff>` component, the one place diff output is styled.
 *
 * Rendered standalone via `Blade::render()`, following
 * {@see AutosaveFieldComponentTest}'s precedent. The component is fed real
 * differ output rather than hand-written fixtures: what it has to cope with is
 * exactly what App\Services\Diff\DiffHtmlRenderer produces, and a fixture that
 * drifts from that would test nothing.
 *
 * The CSS itself lives in `resources/css/app.css`; what is asserted here is the
 * contract between the two — that the component applies the classes those rules
 * hang off, and that the markup carries all three change channels.
 */
class DiffComponentTest extends TestCase
{
    private function render(string $html, string $extra = ''): string
    {
        return Blade::render(
            '<x-diff :html="$html" '.$extra.' />',
            ['html' => $html, 'kind' => FieldKind::Rich],
        );
    }

    private function richDiff(string $old, string $new): string
    {
        return app(RevisionDiffer::class)->diff(FieldKind::Rich, $old, $new)->html;
    }

    public function test_a_change_is_signalled_three_ways_at_once(): void
    {
        $diff = $this->richDiff('<p>The ferry left at dawn.</p>', '<p>The ferry slipped at dawn.</p>');
        $rendered = $this->render($diff, ':kind="$kind"');

        // 1. The class the tint and the gutter glyph both hang off…
        $this->assertStringContainsString('revision-diff', $rendered);
        $this->assertStringContainsString('class="diff-ins"', $rendered);
        $this->assertStringContainsString('class="diff-del"', $rendered);

        // 2. …and the visually-hidden label, so the change is announced rather
        //    than conveyed by colour alone.
        $this->assertStringContainsString('<span class="sr-only">inserted </span>', $rendered);
        $this->assertStringContainsString('<span class="sr-only">removed </span>', $rendered);
    }

    /**
     * The `.revision-diff` rules only, so a failure points at them instead of
     * dumping the whole stylesheet.
     */
    private function diffStyles(): string
    {
        $css = file_get_contents(resource_path('css/app.css'));

        return substr($css, (int) strpos($css, '.revision-diff {'));
    }

    /**
     * One CSS rule block by selector, e.g. `.revision-diff ins.diff-ins`.
     */
    private function rule(string $selector): string
    {
        $styles = $this->diffStyles();
        $start = strpos($styles, $selector.' {');

        $this->assertNotFalse($start, "No rule found for `{$selector}` — the styling contract moved.");

        return substr($styles, $start, (int) strpos($styles, '}', $start) - $start);
    }

    public function test_every_marker_gets_a_gutter_glyph(): void
    {
        // The third channel is a pseudo-element, so it is asserted where it
        // lives. Tint plus label without a glyph would still leave the change
        // invisible to a reader who cannot tell the two tints apart.
        $this->assertStringContainsString("content: '+'", $this->rule('.revision-diff ins.diff-ins::before'));

        // U+2212 MINUS SIGN, not a hyphen: it reads as the counterpart to `+`.
        $this->assertStringContainsString("content: '\\2212'", $this->rule('.revision-diff del.diff-del::before'));
    }

    public function test_the_source_diff_gets_the_glyph_too(): void
    {
        // jfcherng emits bare <ins>/<del> with no class of their own, so the
        // rules keyed on `.diff-ins`/`.diff-del` miss this side entirely. Easy
        // to leave with only its tint, which is one channel, not two.
        $this->assertStringContainsString("content: '+'", $this->rule('.revision-diff--source ins::before'));
        $this->assertStringContainsString("content: '\\2212'", $this->rule('.revision-diff--source del::before'));
    }

    public function test_no_marker_is_ever_drawn_with_a_text_decoration(): void
    {
        // The writer can apply <s> and <u> herself, so a struck-out passage has
        // to keep meaning "she struck this out". Browsers underline <ins> and
        // strike <del> by default, which is why the marker rules clear it.
        // Both markers share one rule block, so this finds it by its last
        // selector — the one the opening brace sits on.
        $markers = $this->rule('.revision-diff del.diff-del');
        $sourceMarkers = $this->rule('.revision-diff--source del');

        $this->assertStringContainsString('text-decoration: none', $markers);
        $this->assertStringContainsString('text-decoration: none', $sourceMarkers);

        // And nothing anywhere in the diff styling strikes anything through.
        $this->assertStringNotContainsString('line-through', $this->diffStyles());
    }

    public function test_the_authors_own_strikethrough_survives_as_content(): void
    {
        // <s> is the writer's, <del> is the diff's. They must not collapse into
        // each other — this is the collision the whole marker vocabulary avoids.
        $diff = $this->richDiff('<p>Chapter three</p>', '<p>Chapter <s>three</s> four</p>');
        $rendered = $this->render($diff, ':kind="$kind"');

        $this->assertStringContainsString('<s>', $rendered);
    }

    public function test_a_formatting_only_change_is_named(): void
    {
        $diff = $this->richDiff('<p>Hello world</p>', '<p>Hello <strong>world</strong></p>');
        $rendered = $this->render($diff, ':kind="$kind"');

        // The visible difference can be a single bolded word, so the diff says
        // out loud what changed rather than leaving the reader to spot it.
        $this->assertStringContainsString('diff-formatting-changed', $rendered);
        $this->assertStringContainsString('class="diff-note"', $rendered);
        $this->assertStringContainsString('formatting changed: bold added', $rendered);
    }

    public function test_a_removed_mark_is_named_too(): void
    {
        $diff = $this->richDiff('<p>Hello <em>world</em></p>', '<p>Hello world</p>');
        $rendered = $this->render($diff, ':kind="$kind"');

        $this->assertStringContainsString('formatting changed: italic removed', $rendered);
    }

    public function test_inline_mode_renders_one_line_with_no_block_chrome(): void
    {
        $summary = app(RevisionSummarizer::class)->summarize(
            FieldKind::Rich,
            '<p>The ferry left at dawn.</p>',
            '<p>The ferry slipped at dawn.</p>',
        );

        $rendered = $this->render($summary->summaryHtml, ':inline="true" :kind="$kind"');

        $this->assertStringContainsString('revision-diff--inline', $rendered);
        $this->assertStringNotContainsString('revision-diff--visual', $rendered);
        // Summary mode drops the author's own block markup entirely.
        $this->assertStringNotContainsString('<p', $rendered);
        $this->assertStringContainsString('<ins', $rendered);
    }

    public function test_a_source_diff_gets_the_two_column_layout_and_a_visual_one_does_not(): void
    {
        $source = app(RevisionDiffer::class)->diff(FieldKind::Markdown, 'Hello world', 'Hello **world**')->html;

        $rendered = Blade::render(
            '<x-diff :html="$html" :kind="$kind" />',
            ['html' => $source, 'kind' => FieldKind::Markdown],
        );

        $this->assertStringContainsString('revision-diff--source', $rendered);
        $this->assertStringContainsString('<table', $rendered);

        $visual = $this->render($this->richDiff('<p>Old</p>', '<p>New</p>'), ':kind="$kind"');

        $this->assertStringContainsString('revision-diff--visual', $visual);
        $this->assertStringNotContainsString('revision-diff--source', $visual);
    }

    public function test_every_field_kind_renders_without_error(): void
    {
        foreach (FieldKind::cases() as $kind) {
            $diff = app(RevisionDiffer::class)->diff($kind, 'before', 'after')->html;

            $rendered = Blade::render(
                '<x-diff :html="$html" :kind="$kind" />',
                ['html' => $diff, 'kind' => $kind],
            );

            $this->assertStringContainsString('revision-diff', $rendered, "{$kind->value} failed to render");
        }
    }

    public function test_an_empty_diff_renders_nothing_at_all(): void
    {
        $this->assertSame('', trim(Blade::render('<x-diff :html="null" />')));
        $this->assertSame('', trim(Blade::render('<x-diff html="" />')));
    }

    public function test_the_component_does_not_sanitize_away_the_markers_it_exists_to_show(): void
    {
        // The author allow-list has no ins/del in it, so anything that purified
        // this input would strip exactly what a diff is for.
        $diff = $this->richDiff('<p>Old text</p>', '<p>New text</p>');
        $rendered = $this->render($diff, ':kind="$kind"');

        $this->assertStringContainsString('<ins', $rendered);
        $this->assertStringContainsString('<del', $rendered);
    }
}
