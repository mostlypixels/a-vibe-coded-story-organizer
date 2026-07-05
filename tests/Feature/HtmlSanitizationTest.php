<?php

namespace Tests\Feature;

use App\Enums\SceneStatus;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Rules\ValidMarkdown;
use App\Support\PlotlineColors;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Proves the core security invariant of the rich-text feature: every rich-HTML field
 * is sanitized server-side on write (via the per-field set-mutators), so the DB can
 * never hold unsafe HTML — regardless of whether the row arrives over HTTP or through
 * a direct Eloquent write (seeder/tinker). Also fills the authorization gaps for the
 * controllers this task touches (Act, Chapter, Plotline, Event).
 */
class HtmlSanitizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A payload mixing the three classic stored-XSS vectors with allowed markup:
     * a <script> tag, an onerror handler on a disallowed <img>, a javascript: href,
     * a style attribute, plus a <strong> and a <ul><li> that must survive.
     */
    private const MALICIOUS_HTML = '<script>alert(1)</script>'
        .'<p style="color:red">styled</p>'
        .'<img src=x onerror=alert(1)>'
        .'<a href="javascript:alert(1)">bad link</a>'
        .'<strong>bold</strong><ul><li>item</li></ul>';

    private function assertSanitized(?string $stored): void
    {
        $this->assertNotNull($stored);
        // The three attack vectors and the presentational attribute are gone.
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onerror', $stored);
        $this->assertStringNotContainsString('javascript:', $stored);
        $this->assertStringNotContainsString('style=', $stored);
        // Allowed markup survives.
        $this->assertStringContainsString('<strong>', $stored);
        $this->assertStringContainsString('<li>', $stored);
    }

    public function test_a_description_is_sanitized_when_stored_over_http(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('projects.acts.store', $project), [
                'name' => 'A sanitized act',
                'description' => self::MALICIOUS_HTML,
            ])
            ->assertRedirect(route('projects.acts.index', $project));

        $this->assertSanitized(Act::first()->description);
    }

    public function test_a_description_is_sanitized_on_a_direct_eloquent_write(): void
    {
        // No HTTP, no Form Request — this is the seeder/tinker path. The set-mutator is
        // the choke point, so the stored value is still clean.
        $project = Project::factory()->create();

        $act = Act::factory()->for($project)->create([
            'description' => self::MALICIOUS_HTML,
        ]);

        $this->assertSanitized($act->fresh()->description);
    }

    public function test_a_sanitized_description_is_not_rendered_as_a_script_tag(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Act::factory()->for($project)->create(['description' => self::MALICIOUS_HTML]);

        // Defense in depth: nothing executable reaches the page.
        $this->actingAs($user)
            ->get(route('projects.acts.index', $project))
            ->assertOk()
            ->assertDontSee('<script>', false)
            ->assertDontSee('onerror', false);
    }

    public function test_scene_notes_is_sanitized_as_html_but_contents_stays_markdown(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();

        $markdownContents = '# A heading

Some **markdown** with a [link](https://example.com).';

        $this->actingAs($user)
            ->post(route('projects.scenes.store', $project), [
                'chapter_id' => $chapter->id,
                'name' => 'A scene',
                'description' => null,
                'contents' => $markdownContents,
                'notes' => self::MALICIOUS_HTML,
                'status' => SceneStatus::Draft->value,
            ])
            ->assertRedirect(route('projects.scenes.index', $project));

        $scene = Scene::first();

        // notes: sanitized rich HTML.
        $this->assertSanitized($scene->notes);

        // contents: stored verbatim as Markdown (not HTML-mangled), still valid Markdown.
        $this->assertSame($markdownContents, $scene->contents);
        $validator = Validator::make(
            ['contents' => $scene->contents],
            ['contents' => [new ValidMarkdown]],
        );
        $this->assertFalse($validator->fails());
    }

    public function test_a_null_description_stays_null_after_sanitization(): void
    {
        // The mutator preserves null so a nullable column is not turned into "".
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create(['description' => null]);

        $this->assertNull($act->fresh()->description);
    }

    // --- Authorization gaps for the controllers this task touches ---

    public function test_owner_can_store_an_act_and_non_owner_cannot(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->post(route('projects.acts.store', $project), [
                'name' => 'Owner act',
                'description' => '<p>Rich <strong>description</strong></p>',
            ])
            ->assertRedirect(route('projects.acts.index', $project));

        $this->actingAs($other)
            ->post(route('projects.acts.store', $project), ['name' => 'Intruder act'])
            ->assertForbidden();

        $this->assertSame(1, $project->acts()->count());
    }

    public function test_owner_can_update_an_act_and_non_owner_cannot(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $act = Act::factory()->for($project)->create(['name' => 'Original']);

        $this->actingAs($owner)
            ->put(route('acts.update', $act), ['name' => 'Renamed', 'description' => '<p>ok</p>'])
            ->assertRedirect(route('projects.acts.index', $project));
        $this->assertSame('Renamed', $act->fresh()->name);

        $this->actingAs($other)
            ->put(route('acts.update', $act), ['name' => 'Hacked'])
            ->assertForbidden();
        $this->assertSame('Renamed', $act->fresh()->name);
    }

    public function test_owner_can_store_a_chapter_and_non_owner_cannot(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $act = Act::factory()->for($project)->create();

        $this->actingAs($owner)
            ->post(route('projects.chapters.store', $project), [
                'act_id' => $act->id,
                'name' => 'Owner chapter',
                'description' => '<p>Rich <em>chapter</em></p>',
            ])
            ->assertRedirect(route('projects.chapters.index', $project));

        $this->actingAs($other)
            ->post(route('projects.chapters.store', $project), [
                'act_id' => $act->id,
                'name' => 'Intruder chapter',
            ])
            ->assertForbidden();

        $this->assertSame(1, $act->chapters()->count());
    }

    public function test_owner_can_update_a_chapter_and_non_owner_cannot(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create(['name' => 'Original']);

        $this->actingAs($owner)
            ->put(route('chapters.update', $chapter), [
                'act_id' => $act->id,
                'name' => 'Renamed',
                'description' => '<p>ok</p>',
            ])
            ->assertRedirect(route('projects.chapters.index', $project));
        $this->assertSame('Renamed', $chapter->fresh()->name);

        $this->actingAs($other)
            ->put(route('chapters.update', $chapter), ['act_id' => $act->id, 'name' => 'Hacked'])
            ->assertForbidden();
        $this->assertSame('Renamed', $chapter->fresh()->name);
    }

    public function test_owner_can_update_a_plotline_and_non_owner_cannot(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $plotline = Plotline::factory()->for($project)->create([
            'name' => 'Original',
            'color' => PlotlineColors::PRESETS[5],
        ]);

        $this->actingAs($owner)
            ->put(route('plotlines.update', $plotline), [
                'name' => 'Renamed',
                'description' => '<p>Rich <strong>plotline</strong></p>',
                'color' => PlotlineColors::PRESETS[5],
            ])
            ->assertRedirect(route('projects.plotlines.index', $project));

        $plotline = $plotline->fresh();
        $this->assertSame('Renamed', $plotline->name);
        $this->assertStringContainsString('<strong>', $plotline->description);

        $this->actingAs($other)
            ->put(route('plotlines.update', $plotline), [
                'name' => 'Hacked',
                'color' => PlotlineColors::PRESETS[5],
            ])
            ->assertForbidden();
        $this->assertSame('Renamed', $plotline->fresh()->name);
    }

    public function test_owner_can_update_an_event_and_non_owner_cannot(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $plotline = $project->plotlines()->first();
        $event = Event::factory()->for($project)->create(['title' => 'Original']);
        $event->plotlines()->attach($plotline);

        $this->actingAs($owner)
            ->put(route('events.update', $event), [
                'title' => 'Renamed',
                'description' => self::MALICIOUS_HTML,
                'event_datetime' => now()->addWeek()->format('Y-m-d H:i:s'),
                'plotlines' => [$plotline->id],
            ])
            ->assertRedirect(route('projects.events.index', $project));

        $event = $event->fresh();
        $this->assertSame('Renamed', $event->title);
        $this->assertSanitized($event->description);

        $this->actingAs($other)
            ->put(route('events.update', $event), [
                'title' => 'Hacked',
                'event_datetime' => now()->addWeek()->format('Y-m-d H:i:s'),
                'plotlines' => [$plotline->id],
            ])
            ->assertForbidden();
        $this->assertSame('Renamed', $event->fresh()->title);
    }
}
