<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A form submit sends CRLF, the autosave endpoint sends the LF a textarea holds
 * in memory. Before NormalizeLineEndings both reached the database as written,
 * so comparing a manual save with an autosave of the same text marked every
 * line as changed.
 */
class LineEndingNormalizationTest extends TestCase
{
    use RefreshDatabase;

    private function sceneFor(User $user): Scene
    {
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();

        return Scene::factory()->for($chapter)->create(['contents' => 'Old contents.']);
    }

    public function test_a_form_submit_stores_crlf_text_with_lf_line_endings(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $this->actingAs($user)->put(route('scenes.update', $scene), [
            'chapter_id' => $scene->chapter_id,
            'name' => 'Opening scene',
            'description' => 'A test scene',
            'contents' => "First line.\r\n\r\nSecond line.",
            'notes' => null,
            'status' => $scene->status->value,
        ])->assertRedirect();

        $this->assertSame("First line.\n\nSecond line.", $scene->fresh()->contents);
    }

    public function test_saving_the_form_over_autosaved_text_records_no_manual_revision(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'scene', 'id' => $scene->id, 'field' => 'contents']),
            ['value' => "First line.\n\nSecond line.", 'base_hash' => hash('sha256', $scene->contents)],
        )->assertOk();

        $before = $scene->revisions()->where('field', 'contents')->count();

        // The same text the autosave just wrote, as the browser would submit it.
        $this->actingAs($user)->put(route('scenes.update', $scene), [
            'chapter_id' => $scene->chapter_id,
            'name' => 'Opening scene',
            'description' => 'A test scene',
            'contents' => "First line.\r\n\r\nSecond line.",
            'notes' => null,
            'status' => $scene->status->value,
        ])->assertRedirect();

        $this->assertSame($before, $scene->revisions()->where('field', 'contents')->count());
    }
}
