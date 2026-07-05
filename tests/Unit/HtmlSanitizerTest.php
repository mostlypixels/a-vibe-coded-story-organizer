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

    public function test_it_removes_images_and_event_handlers(): void
    {
        $output = $this->clean('<img src=x onerror=alert(1)>');

        $this->assertStringNotContainsString('<img', $output);
        $this->assertStringNotContainsString('onerror', $output);
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
