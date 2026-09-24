<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the "import did not finish" note on the project page. The note stays
 * after `imports:purge` deletes the import row.
 */
class ProjectImportNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_project_page_shows_the_note_for_an_unfinished_import(): void
    {
        $project = Project::factory()->create(['import_unfinished' => true]);

        $this->actingAs($project->user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('This import did not finish')
            ->assertSee(route('projects.import-note.dismiss', $project));
    }

    public function test_the_project_page_has_no_note_for_a_normal_project(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($project->user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertDontSee('This import did not finish');
    }

    public function test_the_owner_dismisses_the_note_and_keeps_the_project(): void
    {
        $project = Project::factory()->create(['import_unfinished' => true]);

        $this->actingAs($project->user)
            ->delete(route('projects.import-note.dismiss', $project))
            ->assertRedirect(route('projects.show', $project));

        $this->assertModelExists($project);
        $this->assertFalse($project->refresh()->import_unfinished);
    }

    public function test_a_non_owner_cannot_dismiss_the_note(): void
    {
        $project = Project::factory()->create(['import_unfinished' => true]);

        $this->actingAs(User::factory()->create())
            ->delete(route('projects.import-note.dismiss', $project))
            ->assertForbidden();

        $this->assertTrue($project->refresh()->import_unfinished);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $project = Project::factory()->create(['import_unfinished' => true]);

        $this->delete(route('projects.import-note.dismiss', $project))
            ->assertRedirect(route('login'));

        $this->assertTrue($project->refresh()->import_unfinished);
    }
}
