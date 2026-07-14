<?php

namespace Tests\Unit;

use App\Models\CodexEntry;
use App\Models\Scene;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScenePivotTest extends TestCase
{
    use RefreshDatabase;

    public function test_scene_and_codex_entry_link_round_trips(): void
    {
        $scene = Scene::factory()->create();
        $entry = CodexEntry::factory()->create();

        $scene->codexReferences()->attach($entry);

        $this->assertTrue($scene->codexReferences()->where('codex_entries.id', $entry->id)->exists());
        $this->assertTrue($entry->referencingScenes()->where('scenes.id', $scene->id)->exists());
        $this->assertDatabaseHas('scene_codex_entry', [
            'scene_id' => $scene->id,
            'codex_entry_id' => $entry->id,
        ]);
    }

    public function test_deleting_the_scene_removes_the_pivot_row_but_not_the_entry(): void
    {
        $scene = Scene::factory()->create();
        $entry = CodexEntry::factory()->create();
        $scene->codexReferences()->attach($entry);

        $scene->delete();

        $this->assertDatabaseMissing('scene_codex_entry', [
            'scene_id' => $scene->id,
            'codex_entry_id' => $entry->id,
        ]);
        $this->assertDatabaseHas('codex_entries', ['id' => $entry->id]);
    }

    public function test_deleting_the_codex_entry_removes_the_pivot_row_but_not_the_scene(): void
    {
        $scene = Scene::factory()->create();
        $entry = CodexEntry::factory()->create();
        $scene->codexReferences()->attach($entry);

        $entry->delete();

        $this->assertDatabaseMissing('scene_codex_entry', [
            'scene_id' => $scene->id,
            'codex_entry_id' => $entry->id,
        ]);
        $this->assertDatabaseHas('scenes', ['id' => $scene->id]);
    }
}
