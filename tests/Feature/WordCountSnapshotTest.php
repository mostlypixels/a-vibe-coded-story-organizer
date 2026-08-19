<?php

namespace Tests\Feature;

use App\Enums\SceneStatus;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use App\Models\User;
use App\Models\WordCountSnapshot;
use App\Support\WriterDay;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Record one cumulative total per project and writer day. */
class WordCountSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function emptySceneFor(User $user): Scene
    {
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();

        return Scene::factory()->for($chapter)->create(['contents' => '']);
    }

    private function snapshotsFor(Project $project)
    {
        return WordCountSnapshot::where('project_id', $project->id)
            ->orderBy('recorded_on')
            ->get();
    }

    private function projectOf(Scene $scene): Project
    {
        return $scene->chapter->act->book->project;
    }

    private function hashOf(string $value): string
    {
        return hash('sha256', $value);
    }

    // ---------------------------------------------------------------------
    // Saving
    // ---------------------------------------------------------------------

    public function test_saving_a_scenes_contents_records_todays_cumulative_total(): void
    {
        $user = User::factory()->create();
        $scene = $this->emptySceneFor($user);
        $project = $this->projectOf($scene);

        $this->assertCount(0, $this->snapshotsFor($project));

        $scene->update(['contents' => 'One two three four five']);

        $snapshots = $this->snapshotsFor($project);

        $this->assertCount(1, $snapshots);
        $this->assertSame(5, $snapshots->first()->word_count);
        $this->assertSame(
            (int) $project->sceneQuery()->sum('word_count'),
            $snapshots->first()->word_count,
        );
    }

    public function test_creating_a_scene_that_already_has_words_records_a_row(): void
    {
        $user = User::factory()->create();
        $scene = $this->emptySceneFor($user);
        $project = $this->projectOf($scene);

        $scene->chapter->scenes()->create([
            'name' => 'Second scene',
            'contents' => 'One two three',
            'status' => SceneStatus::Draft,
        ]);

        $this->assertSame(3, $this->snapshotsFor($project)->first()->word_count);
    }

    public function test_two_saves_on_the_same_day_keep_one_row(): void
    {
        $user = User::factory()->create();
        $scene = $this->emptySceneFor($user);
        $project = $this->projectOf($scene);

        $scene->update(['contents' => 'One two three']);
        $scene->update(['contents' => 'One two three four five six']);

        $snapshots = $this->snapshotsFor($project);

        $this->assertCount(1, $snapshots);
        $this->assertSame(6, $snapshots->first()->word_count);
    }

    public function test_the_recorder_updates_a_row_an_eloquent_save_wrote_for_the_same_day(): void
    {
        $user = User::factory()->create();
        $scene = $this->emptySceneFor($user);
        $project = $this->projectOf($scene);

        WordCountSnapshot::factory()->for($project)->create([
            'recorded_on' => WriterDay::dateFor($user),
            'word_count' => 42,
        ]);

        $scene->update(['contents' => 'One two three']);

        $snapshots = $this->snapshotsFor($project);

        // Both paths must store the same 'Y-m-d' string, or the upsert misses
        // the unique key and the day gets a second row.
        $this->assertCount(1, $snapshots);
        $this->assertSame(3, $snapshots->first()->word_count);
        $this->assertSame(
            WriterDay::dateFor($user),
            DB::table('word_count_snapshots')->value('recorded_on'),
        );
    }

    public function test_saving_on_a_second_day_adds_a_row_and_leaves_the_first_alone(): void
    {
        $user = User::factory()->inTimezone('Europe/Paris')->create();
        $scene = $this->emptySceneFor($user);
        $project = $this->projectOf($scene);

        $this->travelTo(CarbonImmutable::parse('2026-08-08 09:00:00', 'UTC'));
        $scene->update(['contents' => 'One two three']);

        $this->travelTo(CarbonImmutable::parse('2026-08-09 09:00:00', 'UTC'));
        $scene->update(['contents' => 'One two three four five']);

        $snapshots = $this->snapshotsFor($project);

        $this->assertCount(2, $snapshots);
        $this->assertSame('2026-08-08', $snapshots[0]->recorded_on->toDateString());
        $this->assertSame(3, $snapshots[0]->word_count);
        $this->assertSame('2026-08-09', $snapshots[1]->recorded_on->toDateString());
        $this->assertSame(5, $snapshots[1]->word_count);
    }

    public function test_saving_only_notes_and_status_records_nothing(): void
    {
        $user = User::factory()->create();
        $scene = $this->emptySceneFor($user);
        $project = $this->projectOf($scene);

        $scene->update(['notes' => 'A note', 'status' => SceneStatus::Final]);

        $this->assertCount(0, $this->snapshotsFor($project));
    }

    // ---------------------------------------------------------------------
    // Deleting — the cascade paths
    // ---------------------------------------------------------------------

    public function test_deleting_a_scene_records_the_lower_total(): void
    {
        $user = User::factory()->create();
        $scene = $this->emptySceneFor($user);
        $project = $this->projectOf($scene);

        $scene->update(['contents' => 'One two three']);
        $scene->delete();

        $this->assertSame(0, $this->snapshotsFor($project)->first()->word_count);
    }

    public function test_deleting_a_chapter_records_the_lower_total(): void
    {
        $user = User::factory()->create();
        $scene = $this->emptySceneFor($user);
        $project = $this->projectOf($scene);

        $scene->update(['contents' => 'One two three']);
        $scene->chapter->delete();

        $this->assertSame(0, $this->snapshotsFor($project)->first()->word_count);
    }

    public function test_deleting_an_act_records_the_lower_total(): void
    {
        $user = User::factory()->create();
        $scene = $this->emptySceneFor($user);
        $project = $this->projectOf($scene);

        $scene->update(['contents' => 'One two three']);
        $scene->chapter->act->delete();

        $this->assertSame(0, $this->snapshotsFor($project)->first()->word_count);
    }

    public function test_deleting_an_act_with_its_chapters_reassigned_leaves_the_total_unchanged(): void
    {
        $user = User::factory()->create();
        $scene = $this->emptySceneFor($user);
        $project = $this->projectOf($scene);
        $book = $project->books()->first();
        $destination = Act::factory()->for($book)->create();

        $scene->update(['contents' => 'One two three']);

        $this->actingAs($user)
            ->delete(route('acts.destroy', $scene->chapter->act), ['move_children_to' => $destination->id])
            ->assertRedirect(route('books.acts.index', $book));

        $this->assertSame(3, $this->snapshotsFor($project)->first()->word_count);
    }

    // ---------------------------------------------------------------------
    // Undo — the reason this is a model hook
    // ---------------------------------------------------------------------

    public function test_reverting_a_revision_records_the_restored_total(): void
    {
        $user = User::factory()->create();
        $scene = $this->emptySceneFor($user);
        $project = $this->projectOf($scene);

        $scene->update(['contents' => 'One two three four five']);

        $old = Revision::factory()->create([
            'revisionable_type' => Scene::class,
            'revisionable_id' => $scene->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'field' => 'contents',
            'value' => 'Two words',
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($user)->post(route('revisions.revert', $old), [
            'base_hash' => $this->hashOf('One two three four five'),
        ])->assertRedirect();

        $this->assertSame(2, $this->snapshotsFor($project)->first()->word_count);
    }

    // ---------------------------------------------------------------------
    // What deliberately does not record
    // ---------------------------------------------------------------------

    public function test_a_bulk_db_table_write_records_nothing(): void
    {
        $user = User::factory()->create();
        $scene = $this->emptySceneFor($user);
        $project = $this->projectOf($scene);

        DB::table('scenes')->where('id', $scene->id)->update(['word_count' => 4200]);

        $this->assertCount(0, $this->snapshotsFor($project));
    }

    // ---------------------------------------------------------------------
    // The writer's day
    // ---------------------------------------------------------------------

    public function test_the_row_is_dated_in_the_owners_timezone_not_the_actors(): void
    {
        // 2026-08-08 23:30 UTC is already 2026-08-09 in Auckland.
        $this->travelTo(CarbonImmutable::parse('2026-08-08 23:30:00', 'UTC'));

        $owner = User::factory()->inTimezone('Pacific/Auckland')->create();
        $scene = $this->emptySceneFor($owner);

        $this->actingAs(User::factory()->inTimezone('America/Los_Angeles')->create());

        $scene->update(['contents' => 'One two three']);

        $this->assertSame(
            '2026-08-09',
            $this->snapshotsFor($this->projectOf($scene))->first()->recorded_on->toDateString(),
        );
    }

    public function test_two_saves_either_side_of_the_owners_local_midnight_produce_two_rows(): void
    {
        $owner = User::factory()->inTimezone('Pacific/Auckland')->create();
        $scene = $this->emptySceneFor($owner);
        $project = $this->projectOf($scene);

        // 23:00 then 01:00 in Auckland, either side of its midnight — one single
        // UTC day, so a server-zone implementation would write one row.
        $this->travelTo(CarbonImmutable::parse('2026-08-08 11:00:00', 'UTC'));
        $scene->update(['contents' => 'One two three']);

        $this->travelTo(CarbonImmutable::parse('2026-08-08 13:00:00', 'UTC'));
        $scene->update(['contents' => 'One two three four five']);

        $snapshots = $this->snapshotsFor($project);

        $this->assertCount(2, $snapshots);
        $this->assertSame('2026-08-08', $snapshots[0]->recorded_on->toDateString());
        $this->assertSame('2026-08-09', $snapshots[1]->recorded_on->toDateString());
    }
}
