<?php

namespace Tests\Unit;

use App\Enums\RichTextProfile;
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

    private function cleanStructural(string $html): string
    {
        return app(HtmlSanitizer::class)->clean($html, RichTextProfile::Structural);
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
        $output = $this->clean('<p><strong>bold</strong> and <em>italic</em> and <u>under</u> and <s>strike</s>'
            .' and <sub>sub</sub> and <sup>sup</sup></p>');

        $this->assertStringContainsString('<strong>bold</strong>', $output);
        $this->assertStringContainsString('<em>italic</em>', $output);
        $this->assertStringContainsString('<u>under</u>', $output);
        $this->assertStringContainsString('<s>strike</s>', $output);
        $this->assertStringContainsString('<sub>sub</sub>', $output);
        $this->assertStringContainsString('<sup>sup</sup>', $output);
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
        // colspan/rowspan are in ALLOWED_ATTRIBUTES, so a merged cell (from the
        // toolbar's mergeCells/splitCell buttons, or hand-authored HTML)
        // round-trips through the server. The editor never emits
        // `style`/<colgroup>/<col> for tables (see wysiwyg.js's PlainTable
        // override), so this proves only that colspan/rowspan survive. A stray
        // style or colgroup from another path is still stripped.
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

    public function test_decorative_classes_derive_from_the_registry(): void
    {
        $classes = RichTextFields::decorativeClasses();

        $this->assertContains('rt-align-center', $classes);
        $this->assertContains('rt-color-red', $classes);
        $this->assertNotContains('rt-align-left', $classes, 'Left is the default and writes no class.');
        $this->assertCount(
            count(RichTextFields::ALIGNMENTS) + count(RichTextFields::TEXT_COLORS),
            $classes,
        );
    }

    public function test_every_alignable_tag_may_carry_a_class(): void
    {
        foreach (RichTextFields::ALIGNABLE_TAGS as $tag) {
            $this->assertContains('class', RichTextFields::ALLOWED_ATTRIBUTES[$tag] ?? [], $tag);
        }

        $this->assertContains('class', RichTextFields::ALLOWED_ATTRIBUTES['span']);
    }

    /**
     * Looping the registry is the point: a colour added later cannot escape this
     * test by being absent from a literal list.
     */
    public function test_the_rich_profile_keeps_every_decorative_class(): void
    {
        foreach (RichTextFields::ALIGNMENTS as $alignment) {
            $output = $this->clean('<p class="rt-align-'.$alignment.'">text</p>');
            $this->assertStringContainsString('class="rt-align-'.$alignment.'"', $output);

            $output = $this->clean('<h2 class="rt-align-'.$alignment.'">head</h2>');
            $this->assertStringContainsString('class="rt-align-'.$alignment.'"', $output);
        }

        foreach (RichTextFields::TEXT_COLORS as $color) {
            $output = $this->clean('<p><span class="rt-color-'.$color.'">text</span></p>');
            $this->assertStringContainsString('class="rt-color-'.$color.'"', $output);
        }
    }

    public function test_the_structural_profile_strips_every_decorative_class(): void
    {
        foreach (RichTextFields::decorativeClasses() as $class) {
            $output = $this->cleanStructural('<p class="'.$class.'"><span class="'.$class.'">text</span></p>');

            $this->assertStringNotContainsString('class=', $output, $class);
            $this->assertStringContainsString('text', $output);
        }
    }

    public function test_it_strips_an_unregistered_decorative_class_but_keeps_the_element(): void
    {
        foreach ([$this->clean(...), $this->cleanStructural(...)] as $clean) {
            $output = $clean('<p class="rt-color-chartreuse">text</p><span class="prose">more</span>');

            $this->assertStringNotContainsString('chartreuse', $output);
            $this->assertStringNotContainsString('prose', $output);
            $this->assertStringContainsString('<p>', $output);
            $this->assertStringContainsString('text', $output);
            $this->assertStringContainsString('more', $output);
        }
    }

    public function test_it_strips_presentational_styles_under_both_profiles(): void
    {
        foreach ([$this->clean(...), $this->cleanStructural(...)] as $clean) {
            $output = $clean('<p style="text-align: center"><span style="color: red">text</span></p>');

            $this->assertStringNotContainsString('style=', $output);
            $this->assertStringContainsString('text', $output);
        }
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
        $this->assertStringContainsString('p[class]', $allowed);
        $this->assertStringContainsString('span[class]', $allowed);
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
