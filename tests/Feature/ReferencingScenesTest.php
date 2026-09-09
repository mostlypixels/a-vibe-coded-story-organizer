<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Book;
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

    public function test_unassigned_scenes_are_ordered_by_book_before_act(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();

        // The project's first book already holds position 1.
        $secondBook = Book::factory()->for($project)->create();

        $actInBookOne = Act::factory()->for($project->books()->first())->create(['position' => 2]);
        $chapterInBookOne = Chapter::factory()->for($actInBookOne)->create(['position' => 1]);
        $sceneInBookOne = Scene::factory()->for($chapterInBookOne)->create(['name' => 'Scene in book one', 'position' => 1]);

        $actInBookTwo = Act::factory()->for($secondBook)->create(['position' => 1]);
        $chapterInBookTwo = Chapter::factory()->for($actInBookTwo)->create(['position' => 1]);
        $sceneInBookTwo = Scene::factory()->for($chapterInBookTwo)->create(['name' => 'Scene in book two', 'position' => 1]);

        $entry->referencingScenes()->attach([$sceneInBookTwo->id, $sceneInBookOne->id]);

        $ordered = (new ReferencingScenes)->forEntry($entry);

        $this->assertSame(['Scene in book one', 'Scene in book two'], $ordered->pluck('name')->all());
    }

    public function test_unassigned_scenes_still_sort_after_every_evented_scene(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();

        $secondBook = Book::factory()->for($project)->create();
        $event = Event::factory()->for($project)->create(['event_datetime' => now()]);

        $eventedScene = $this->sceneIn($project, 'Scene with an event', $event);

        $actInBookTwo = Act::factory()->for($secondBook)->create(['position' => 1]);
        $chapterInBookTwo = Chapter::factory()->for($actInBookTwo)->create(['position' => 1]);
        $unassignedScene = Scene::factory()->for($chapterInBookTwo)->create(['name' => 'Unassigned scene in book two', 'position' => 1]);

        $entry->referencingScenes()->attach([$unassignedScene->id, $eventedScene->id]);

        $ordered = (new ReferencingScenes)->forEntry($entry);

        $this->assertSame(
            ['Scene with an event', 'Unassigned scene in book two'],
            $ordered->pluck('name')->all(),
        );
    }

    public function test_for_scene_orders_referenced_entries_by_type_then_name(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $scene = $this->sceneIn($project, 'A scene');

        $zebraLocation = CodexEntry::factory()->for($project)->location()->create(['name' => 'Zebra Plains']);
        $ashCharacter = CodexEntry::factory()->for($project)->character()->create(['name' => 'Ash']);
        $bellCharacter = CodexEntry::factory()->for($project)->character()->create(['name' => 'Bell']);

        $scene->codexReferences()->attach([$zebraLocation->id, $bellCharacter->id, $ashCharacter->id]);

        $ordered = (new ReferencingScenes)->forScene($scene);

        $this->assertSame(['Ash', 'Bell', 'Zebra Plains'], $ordered->pluck('name')->all());
    }
}
