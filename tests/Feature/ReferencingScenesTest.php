<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Services\ReferencingScenes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferencingScenesTest extends TestCase
{
    use RefreshDatabase;

    private function sceneIn(Project $project, string $name, ?Event $event = null): Scene
    {
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();

        $attributes = ['name' => $name];

        if ($event !== null) {
            $attributes['event_id'] = $event->id;
        }

        return Scene::factory()->for($chapter)->create($attributes);
    }

    public function test_scenes_with_an_event_come_first_ordered_by_event_datetime_then_id(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();

        // Deliberately created in the opposite order to their datetimes — only the
        // (event_datetime, id) sort passes.
        $laterEvent = Event::factory()->for($project)->create(['event_datetime' => now()->addDays(10)]);
        $earlierEvent = Event::factory()->for($project)->create(['event_datetime' => now()->addDays(2)]);

        $laterScene = $this->sceneIn($project, 'Scene at the coronation', $laterEvent);
        $earlierScene = $this->sceneIn($project, 'Scene at the betrothal', $earlierEvent);
        $unassignedScene = $this->sceneIn($project, 'Scene without an event');

        $entry->referencingScenes()->attach([$unassignedScene->id, $laterScene->id, $earlierScene->id]);

        $ordered = (new ReferencingScenes)->forEntry($entry);

        $this->assertSame(
            ['Scene at the betrothal', 'Scene at the coronation', 'Scene without an event'],
            $ordered->pluck('name')->all(),
        );
    }

    public function test_unassigned_scenes_are_ordered_by_act_chapter_and_scene_position(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();

        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create(['position' => 1]);
        $chapter = Chapter::factory()->for($act)->create(['position' => 1]);

        $first = Scene::factory()->for($chapter)->create(['name' => 'First scene', 'position' => 1]);
        $second = Scene::factory()->for($chapter)->create(['name' => 'Second scene', 'position' => 2]);

        $entry->referencingScenes()->attach([$second->id, $first->id]);

        $ordered = (new ReferencingScenes)->forEntry($entry);

        $this->assertSame(['First scene', 'Second scene'], $ordered->pluck('name')->all());
    }
}
