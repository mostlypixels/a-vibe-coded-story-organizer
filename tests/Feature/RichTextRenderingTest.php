<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers task 03 — safe rendering. Content is sanitized on write (task 02), so the
 * read views render it with {!! !!} exclusively through x-rich-text, while index
 * tables show an escaped, tag-stripped text excerpt via x-rich-text-excerpt. These
 * HTTP-level tests prove allowed formatting survives to the page, nothing executable
 * leaks even if a script was posted, and the read routes stay behind ProjectPolicy.
 */
class RichTextRenderingTest extends TestCase
{
    use RefreshDatabase;

    private const MALICIOUS_HTML = '<script>alert(1)</script>'
        .'<p style="color:red">styled</p>'
        .'<a href="javascript:alert(1)">bad link</a>'
        .'<strong>bold text</strong><ul><li>a list item</li></ul>';

    public function test_project_show_renders_allowed_formatting_but_no_script(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['description' => self::MALICIOUS_HTML]);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            // Allowed markup rendered as real HTML through x-rich-text.
            ->assertSee('<strong>bold text</strong>', false)
            ->assertSee('<li>a list item</li>', false)
            // The <script> posted pre-sanitization never reaches the page.
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('href="javascript:', false);
    }

    public function test_acts_index_renders_an_escaped_text_excerpt_not_raw_html(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Act::factory()->for($project)->create([
            'name' => 'An act with a rich description',
            'description' => self::MALICIOUS_HTML,
        ]);

        $this->actingAs($user)
            ->get(route('projects.acts.index', $project))
            ->assertOk()
            // The excerpt strips tags, so the readable text shows...
            ->assertSee('bold text')
            // ...but no markup (safe or otherwise) leaks into the table cell.
            ->assertDontSee('<strong>', false)
            ->assertDontSee('<li>', false)
            ->assertDontSee('<script>', false);
    }

    public function test_non_owner_cannot_view_a_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create(['description' => '<strong>secret</strong>']);

        $this->actingAs($owner)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('<strong>secret</strong>', false);

        $this->actingAs($other)
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }
}
