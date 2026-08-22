<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Database\Seeders\MelusineSeederEn;
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
}
