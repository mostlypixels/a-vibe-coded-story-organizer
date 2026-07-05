<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers task 04 — the WYSIWYG editor UI. The editor is progressive enhancement:
 * x-wysiwyg renders a real, submittable <textarea> that Alpine mounts Tiptap over.
 * These HTTP-level tests prove every swapped create/edit form still renders that
 * underlying textarea (name="description"/"notes"), so a JS-off submit — and old()
 * repopulation on validation failure — stays intact. They do not exercise the JS.
 */
class WysiwygFormTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{User, Project, Act, Chapter, Scene, Plotline, Event, CodexEntry}
     */
    private function fixture(): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create();
        $plotline = Plotline::factory()->for($project)->create();
        $event = Event::factory()->for($project)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();

        return [$user, $project, $act, $chapter, $scene, $plotline, $event, $entry];
    }

    private function assertHasTextarea(string $url, User $user, string $field): void
    {
        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertSee('<textarea', false)
            ->assertSee('name="'.$field.'"', false);
    }

    public function test_every_swapped_form_renders_a_submittable_textarea(): void
    {
        [$user, $project, $act, $chapter, $scene, $plotline, $event, $entry] = $this->fixture();

        // Create forms (nested under the project) and edit forms (flat), all carrying
        // the progressive-enhancement <textarea> for the rich-HTML `description` field.
        $descriptionForms = [
            route('projects.create'),
            route('projects.edit', $project),
            route('projects.acts.create', $project),
            route('acts.edit', $act),
            route('projects.chapters.create', $project),
            route('chapters.edit', $chapter),
            route('projects.plotlines.create', $project),
            route('plotlines.edit', $plotline),
            route('projects.events.create', $project),
            route('events.edit', $event),
            route('projects.scenes.create', $project),
            route('scenes.edit', $scene),
            route('projects.codex.create', [$project, 'characters']),
            route('codex.edit', $entry),
        ];

        foreach ($descriptionForms as $url) {
            $this->assertHasTextarea($url, $user, 'description');
        }

        // Scene `notes` also became a rich-HTML editor; its textarea must survive too.
        $this->assertHasTextarea(route('projects.scenes.create', $project), $user, 'notes');
        $this->assertHasTextarea(route('scenes.edit', $scene), $user, 'notes');
    }

    public function test_scene_forms_keep_contents_as_a_markdown_textarea(): void
    {
        [$user, $project, , , $scene] = $this->fixture();

        // Hard spec requirement: Scene `contents` stays a plain Markdown textarea with
        // its "(Markdown)" label and font-mono styling — never the WYSIWYG editor.
        $this->actingAs($user)
            ->get(route('projects.scenes.create', $project))
            ->assertOk()
            ->assertSee('Contents (Markdown)')
            ->assertSee('name="contents"', false);

        $this->actingAs($user)
            ->get(route('scenes.edit', $scene))
            ->assertOk()
            ->assertSee('Contents (Markdown)')
            ->assertSee('name="contents"', false);
    }
}
