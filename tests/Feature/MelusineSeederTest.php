<?php

namespace Tests\Feature;

use App\Models\CodexEntry;
use App\Models\EntityLink;
use App\Models\Notable;
use App\Models\Note;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Services\StarterNoteCategories;
use Database\Seeders\MelusineSeederEn;
use Database\Seeders\MelusineSeederFr;
use Database\Seeders\MelusineSeederIt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MelusineSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The seeder runs through the seeder infrastructure in real use: `db:seed`
     * unguards models (so `user_id` can be set, though it is not fillable) and
     * the demo command turns model events off (so `Project::created` does not
     * build a second book). The test reproduces both.
     */
    private function seedFor(User $user): void
    {
        Model::unguarded(fn () => Model::withoutEvents(
            fn () => app(MelusineSeederEn::class)->forUser($user)->run(),
        ));
    }

    public function test_it_attaches_the_project_to_the_given_user(): void
    {
        $owner = User::factory()->create();
        User::factory()->create(); // A different first user, to prove the target wins.

        $this->seedFor($owner);

        $project = Project::where('name', 'The Roman of Melusine')->firstOrFail();

        $this->assertSame($owner->id, $project->user_id);
    }

    public function test_re_running_for_the_same_user_is_a_no_op(): void
    {
        $owner = User::factory()->create();

        $this->seedFor($owner);
        $this->seedFor($owner);

        $this->assertSame(1, $owner->projects()->where('name', 'The Roman of Melusine')->count());
    }

    public function test_each_demo_project_gets_starter_categories_and_linked_notes(): void
    {
        $owner = User::factory()->create();

        Model::unguarded(fn () => Model::withoutEvents(function () use ($owner): void {
            app(MelusineSeederEn::class)->forUser($owner)->run();
            app(MelusineSeederFr::class)->forUser($owner)->run();
            app(MelusineSeederIt::class)->forUser($owner)->run();
        }));

        $this->assertCount(3, $owner->projects);

        foreach ($owner->projects as $project) {
            $this->assertEqualsCanonicalizing(
                [...StarterNoteCategories::NAMES, 'nested'],
                $project->noteCategories->map(fn ($c) => $c->parent_id === null ? $c->name : 'nested')->unique()->values()->all(),
            );
            $this->assertCount(3, $project->notes);

            // One category is nested under a starter, and a note sits in it.
            $nested = $project->noteCategories->firstWhere(fn ($c) => $c->parent_id !== null);
            $this->assertSame($project->noteCategories->firstWhere('name', 'Research')->id, $nested->parent_id);
            $this->assertSame(1, $project->notes->where('note_category_id', $nested->id)->count());

            $linked = Notable::query()->whereIn('note_id', $project->notes->pluck('id'))->get();
            $this->assertEqualsCanonicalizing(
                [(new Scene)->getMorphClass(), (new CodexEntry)->getMorphClass()],
                $linked->pluck('notable_type')->unique()->values()->all(),
            );
            $this->assertCount(3, $linked);
        }
    }

    /** Model events are off while seeding, so the seeder syncs the index itself. */
    public function test_the_seed_note_link_to_a_scene_has_its_index_row(): void
    {
        $owner = User::factory()->create();

        $this->seedFor($owner);

        $note = Note::where('title', 'The Saturday curse')->firstOrFail();
        $scene = $note->project->sceneQuery()->where('scenes.name', 'What Raymondin Saw')->firstOrFail();

        $this->assertStringContainsString('href="entity:scene/'.$scene->id.'"', $note->body);
        $this->assertSame(
            [['body', 'scene', $scene->id]],
            EntityLink::query()->where('source_type', $note->getMorphClass())->where('source_id', $note->id)
                ->get()->map(fn (EntityLink $row): array => [$row->source_field, $row->target_type, $row->target_id])->all(),
        );
    }

    public function test_re_running_keeps_the_demo_notes_single(): void
    {
        $owner = User::factory()->create();

        $this->seedFor($owner);
        $this->seedFor($owner);

        $this->assertSame(3, Note::count());
    }
}
