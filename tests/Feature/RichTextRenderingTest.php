<?php

namespace Tests\Feature;

use App\Enums\BookLanguage;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Safe rendering. Content is sanitized on write, so the read views render it with
 * {!! !!} exclusively through x-rich-text, while index tables show an escaped,
 * tag-stripped text excerpt via x-rich-text-excerpt.
 *
 * These HTTP-level tests prove that allowed formatting reaches the page, that
 * nothing executable leaks when someone posts a script, and that the read routes
 * stay behind ProjectPolicy.
 */
class RichTextRenderingTest extends TestCase
{
    use RefreshDatabase;

    private const MALICIOUS_HTML = '<script>alert(1)</script>'
        .'<p style="color:red">styled</p>'
        .'<a href="javascript:alert(1)">bad link</a>'
        .'<strong>bold text</strong><ul><li>a list item</li></ul>';

    public function test_a_shared_scene_renders_allowed_formatting_but_no_script(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create(['description' => self::MALICIOUS_HTML]);
        $scene->forceFill([
            'share_token' => 'rich-text-token',
            'share_expires_at' => now()->addDay(),
        ])->save();

        $this->get(route('shared.scenes.show', 'rich-text-token'))
            ->assertOk()
            // Allowed markup rendered as real HTML through x-rich-text.
            ->assertSee('<strong>bold text</strong>', false)
            ->assertSee('<li>a list item</li>', false)
            // The <script> posted pre-sanitization never reaches the page.
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('href="javascript:', false);
    }

    public function test_a_shared_scene_renders_the_manuscript_face_on_its_prose(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create(['description' => '<p>text</p>']);
        $scene->forceFill([
            'share_token' => 'font-manuscript-token',
            'share_expires_at' => now()->addDay(),
        ])->save();

        $this->get(route('shared.scenes.show', 'font-manuscript-token'))
            ->assertOk()
            ->assertSee('class="prose prose-sm font-manuscript max-w-none text-content-muted"', false);
    }

    public function test_acts_index_renders_an_escaped_text_excerpt_not_raw_html(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        Act::factory()->for($book)->create([
            'name' => 'An act with a rich description',
            'description' => self::MALICIOUS_HTML,
        ]);

        $this->actingAs($user)
            ->get(route('books.acts.index', $book))
            ->assertOk()
            // The excerpt strips tags, so the readable text shows...
            ->assertSee('bold text')
            // ...but no markup (safe or otherwise) leaks into the table cell.
            ->assertDontSee('<strong>', false)
            ->assertDontSee('<li>', false)
            ->assertDontSee('<script>', false);
    }

    public function test_project_update_persists_a_table_and_image_fragment_unchanged(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $richFragment = '<table><thead><tr><th>Col</th></tr></thead>'
            .'<tbody><tr><td>Val</td></tr></tbody></table>'
            .'<img src="https://example.com/cover.png" alt="Cover">';

        $this->actingAs($user)
            ->put(route('projects.update', $project), [
                'name' => $project->name,
                'description' => $richFragment,
                'language' => BookLanguage::English->value,
            ])
            ->assertRedirect();

        $this->assertStringContainsString('<table>', $project->fresh()->description);
        $this->assertStringContainsString('<th>Col</th>', $project->fresh()->description);
        $this->assertStringContainsString('src="https://example.com/cover.png"', $project->fresh()->description);
    }

    public function test_non_owner_cannot_view_a_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create(['description' => '<strong>secret</strong>']);

        $this->actingAs($owner)
            ->get(route('projects.show', $project))
            ->assertOk();

        $this->actingAs($other)
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }
}
