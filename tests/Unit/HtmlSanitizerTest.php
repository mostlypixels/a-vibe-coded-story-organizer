<?php

namespace Tests\Unit;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Services\HtmlSanitizer;
use App\Support\RichTextFields;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    private function clean(string $html): string
    {
        return app(HtmlSanitizer::class)->clean($html);
    }

    public function test_it_strips_script_tags(): void
    {
        $output = $this->clean('<p>hi</p><script>alert(1)</script>');

        $this->assertStringNotContainsString('<script', $output);
        $this->assertStringNotContainsString('alert(1)', $output);
        $this->assertStringContainsString('hi', $output);
    }

    public function test_it_keeps_images_but_strips_event_handlers(): void
    {
        // expand-tip-tap: <img> joined the allow-list (src/alt/title/width/height),
        // but only those attribute names — onerror is not in ALLOWED_ATTRIBUTES so
        // it's stripped regardless of tag.
        $output = $this->clean('<img src="https://example.com/x.png" alt="x" onerror=alert(1)>');

        $this->assertStringContainsString('<img', $output);
        $this->assertStringContainsString('src="https://example.com/x.png"', $output);
        $this->assertStringContainsString('alt="x"', $output);
        $this->assertStringNotContainsString('onerror', $output);
    }

    public function test_it_strips_disallowed_image_url_schemes(): void
    {
        $output = $this->clean('<img src="javascript:alert(1)" alt="x">');

        $this->assertStringNotContainsString('javascript:', $output);
    }

    public function test_it_neutralizes_javascript_hrefs_but_keeps_the_text(): void
    {
        $output = $this->clean('<a href="javascript:alert(1)">click</a>');

        $this->assertStringNotContainsString('javascript:', $output);
        $this->assertStringContainsString('click', $output);
    }

    public function test_it_strips_data_uri_hrefs(): void
    {
        $output = $this->clean('<a href="data:text/html,<script>alert(1)</script>">x</a>');

        $this->assertStringNotContainsString('data:', $output);
        $this->assertStringNotContainsString('<script', $output);
    }

    public function test_it_strips_style_attributes(): void
    {
        $output = $this->clean('<p style="position:fixed;color:red">styled</p>');

        $this->assertStringNotContainsString('style=', $output);
        $this->assertStringContainsString('styled', $output);
    }

    public function test_it_strips_user_supplied_classes(): void
    {
        $output = $this->clean('<p class="evil">text</p>');

        $this->assertStringNotContainsString('class=', $output);
        $this->assertStringContainsString('text', $output);
    }

    public function test_it_removes_iframes_and_objects(): void
    {
        $output = $this->clean('<iframe src="https://evil.test"></iframe><object data="x"></object>ok');

        $this->assertStringNotContainsString('<iframe', $output);
        $this->assertStringNotContainsString('<object', $output);
        $this->assertStringContainsString('ok', $output);
    }

    public function test_it_preserves_allowed_inline_markup(): void
    {
        $output = $this->clean('<p><strong>bold</strong> and <em>italic</em> and <u>under</u> and <s>strike</s></p>');

        $this->assertStringContainsString('<strong>bold</strong>', $output);
        $this->assertStringContainsString('<em>italic</em>', $output);
        $this->assertStringContainsString('<u>under</u>', $output);
        $this->assertStringContainsString('<s>strike</s>', $output);
    }

    public function test_it_preserves_allowed_block_markup(): void
    {
        $output = $this->clean('<h2>Title</h2><ul><li>one</li><li>two</li></ul><blockquote>quote</blockquote><pre><code>code</code></pre>');

        $this->assertStringContainsString('<h2>Title</h2>', $output);
        $this->assertStringContainsString('<li>one</li>', $output);
        $this->assertStringContainsString('<blockquote>', $output);
        $this->assertStringContainsString('<pre>', $output);
        $this->assertStringContainsString('<code>', $output);
    }

    public function test_it_keeps_safe_links(): void
    {
        $output = $this->clean('<a href="https://example.com">safe</a>');

        $this->assertStringContainsString('href="https://example.com"', $output);
        $this->assertStringContainsString('safe', $output);
    }

    public function test_it_preserves_a_table_fragment_unchanged(): void
    {
        $table = '<table><thead><tr><th>Name</th></tr></thead>'
            .'<tbody><tr><td>Value</td></tr></tbody></table>';

        $output = $this->clean($table);

        $this->assertStringContainsString('<table>', $output);
        $this->assertStringContainsString('<thead>', $output);
        $this->assertStringContainsString('<tbody>', $output);
        $this->assertStringContainsString('<th>Name</th>', $output);
        $this->assertStringContainsString('<td>Value</td>', $output);
    }

    public function test_it_preserves_a_merged_table_cell(): void
    {
        // expand-tip-tap task 04: colspan/rowspan joined ALLOWED_ATTRIBUTES so a
        // merged cell (produced by the toolbar's mergeCells/splitCell buttons, or
        // hand-authored HTML) round-trips through the server. The editor itself
        // never emits `style`/<colgroup>/<col> for tables (see wysiwyg.js's
        // PlainTable override), so this only needs to prove colspan/rowspan survive
        // — a stray style/colgroup arriving via some other path should still be
        // stripped, same as any other presentational attribute.
        $table = '<table><tbody>'
            .'<tr><td colspan="2" rowspan="1">merged</td></tr>'
            .'<tr><td colspan="1" rowspan="1">a</td><td colspan="1" rowspan="1">b</td></tr>'
            .'</tbody></table>';

        $output = $this->clean($table.'<table style="min-width: 50px;"><colgroup><col></colgroup><tbody><tr><td>x</td></tr></tbody></table>');

        $this->assertStringContainsString('colspan="2"', $output);
        $this->assertStringContainsString('rowspan="1"', $output);
        $this->assertStringContainsString('merged', $output);
        $this->assertStringNotContainsString('style=', $output);
        $this->assertStringNotContainsString('<colgroup', $output);
        $this->assertStringNotContainsString('<col>', $output);
    }

    public function test_it_preserves_a_task_list_fragment_unchanged(): void
    {
        $taskList = '<ul data-type="taskList">'
            .'<li data-type="taskItem" data-checked="true">'
            .'<label><input type="checkbox" checked></label><span></span><div>Done</div>'
            .'</li></ul>';

        $output = $this->clean($taskList);

        $this->assertStringContainsString('data-type="taskList"', $output);
        $this->assertStringContainsString('data-type="taskItem"', $output);
        $this->assertStringContainsString('data-checked="true"', $output);
        $this->assertStringContainsString('type="checkbox"', $output);
        $this->assertStringContainsString('Done', $output);
    }

    public function test_it_preserves_a_callout_blockquote_attribute(): void
    {
        $output = $this->clean('<blockquote data-callout-type="warning"><p>Heads up.</p></blockquote>');

        $this->assertStringContainsString('data-callout-type="warning"', $output);
        $this->assertStringContainsString('Heads up.', $output);
    }

    public function test_purifier_allowed_html_lists_the_new_tags_and_attributes(): void
    {
        $allowed = RichTextFields::purifierAllowedHtml();

        $this->assertStringContainsString('table', $allowed);
        $this->assertStringContainsString('img[src|alt|title|width|height]', $allowed);
        $this->assertStringContainsString('li[data-type|data-checked]', $allowed);
        $this->assertStringContainsString('ul[data-type]', $allowed);
        $this->assertStringContainsString('blockquote[data-callout-type]', $allowed);
        $this->assertStringContainsString('td[colspan|rowspan]', $allowed);
        $this->assertStringContainsString('th[colspan|rowspan]', $allowed);
    }

    public function test_rich_text_fields_exposes_the_expected_field_list(): void
    {
        $this->assertSame([
            'Project.description',
            'Act.description',
            'Chapter.description',
            'Plotline.description',
            'Event.description',
            'Scene.description',
            'Scene.notes',
            'CodexEntry.description',
        ], RichTextFields::all());
    }

    public function test_rich_text_fields_scene_contents_is_not_rich(): void
    {
        $this->assertTrue(RichTextFields::isRich(Scene::class, 'notes'));
        $this->assertTrue(RichTextFields::isRich(Scene::class, 'description'));
        $this->assertFalse(RichTextFields::isRich(Scene::class, 'contents'));
    }

    public function test_rich_text_fields_covers_every_rich_model(): void
    {
        foreach ([Project::class, Act::class, Chapter::class, Plotline::class, Event::class, CodexEntry::class] as $model) {
            $this->assertContains('description', RichTextFields::forModel($model));
        }
    }
}
