<?php

namespace Tests\Feature\Console;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Project;
use App\Models\Scene;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncCodexReferencesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeScene(Project $project, string $contents): Scene
    {
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();

        return Scene::factory()->for($chapter)->create(['contents' => $contents]);
    }

    public function test_it_syncs_every_project_by_default(): void
    {
        $projectOne = Project::factory()->create();
        $entryOne = CodexEntry::factory()->for($projectOne)->create(['name' => 'Aurora']);
        $sceneOne = $this->makeScene($projectOne, 'Aurora stepped outside.');

        $projectTwo = Project::factory()->create();
        $entryTwo = CodexEntry::factory()->for($projectTwo)->create(['name' => 'Orion']);
        $sceneTwo = $this->makeScene($projectTwo, 'Orion looked up at the sky.');

        $this->artisan('codex:sync-references')->assertSuccessful();

        $this->assertDatabaseHas('scene_codex_entry', [
            'scene_id' => $sceneOne->id,
            'codex_entry_id' => $entryOne->id,
        ]);
        $this->assertDatabaseHas('scene_codex_entry', [
            'scene_id' => $sceneTwo->id,
            'codex_entry_id' => $entryTwo->id,
        ]);
    }

    public function test_it_can_be_scoped_to_a_single_project(): void
    {
        $target = Project::factory()->create();
        $targetEntry = CodexEntry::factory()->for($target)->create(['name' => 'Aurora']);
        $targetScene = $this->makeScene($target, 'Aurora stepped outside.');

        $other = Project::factory()->create();
        $otherEntry = CodexEntry::factory()->for($other)->create(['name' => 'Orion']);
        $otherScene = $this->makeScene($other, 'Orion looked up at the sky.');

        $this->artisan('codex:sync-references', ['project' => $target->id])->assertSuccessful();

        $this->assertDatabaseHas('scene_codex_entry', [
            'scene_id' => $targetScene->id,
            'codex_entry_id' => $targetEntry->id,
        ]);
        $this->assertDatabaseMissing('scene_codex_entry', [
            'scene_id' => $otherScene->id,
            'codex_entry_id' => $otherEntry->id,
        ]);
    }

    public function test_it_fails_for_an_unknown_project_id(): void
    {
        $this->artisan('codex:sync-references', ['project' => 999999])->assertFailed();
    }
}
