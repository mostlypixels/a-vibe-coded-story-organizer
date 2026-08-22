<?php

namespace Tests\Feature\Console;

use App\Enums\Genre;
use App\Models\CodexAttribute;
use App\Models\Project;
use App\Models\User;
use App\Support\Bundles\Bundles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedProjectCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_produces_the_same_bundle_as_the_action(): void
    {
        $user = User::factory()->create(['email' => 'writer@example.com']);

        $this->artisan('app:seed-project', [
            '--user' => 'writer@example.com',
            '--genre' => 'fantasy',
            '--name' => 'Dragon tale',
        ])->assertSuccessful();

        $project = Project::where('name', 'Dragon tale')->firstOrFail();

        $this->assertSame($user->id, $project->user_id);
        $this->assertSame(Genre::Fantasy, $project->genre);

        $bundle = Bundles::for(Genre::Fantasy);
        foreach ($bundle->attributes() as $attribute) {
            $this->assertTrue(
                CodexAttribute::where('project_id', $project->id)->where('name', $attribute->name)->exists(),
            );
        }
    }

    public function test_it_resolves_the_user_by_id(): void
    {
        $user = User::factory()->create();

        $this->artisan('app:seed-project', [
            '--user' => (string) $user->id,
            '--genre' => 'blank',
            '--name' => 'Blank book',
        ])->assertSuccessful();

        $this->assertDatabaseHas('projects', ['name' => 'Blank book', 'user_id' => $user->id]);
    }

    public function test_it_defaults_to_the_first_user_when_no_user_option_is_given(): void
    {
        $first = User::factory()->create();
        User::factory()->create();

        $this->artisan('app:seed-project', [
            '--genre' => 'blank',
            '--name' => 'Default owner',
        ])->assertSuccessful();

        $this->assertDatabaseHas('projects', ['name' => 'Default owner', 'user_id' => $first->id]);
    }

    public function test_it_fails_for_an_unknown_genre(): void
    {
        $user = User::factory()->create();

        $this->artisan('app:seed-project', [
            '--user' => (string) $user->id,
            '--genre' => 'space-opera',
            '--name' => 'Whatever',
        ])
            ->expectsOutputToContain('Unknown genre "space-opera". Allowed genres: contemporary, historical, fantasy, science_fiction, blank.')
            ->assertFailed();
    }

    public function test_it_fails_when_name_is_missing(): void
    {
        $user = User::factory()->create();

        $this->artisan('app:seed-project', [
            '--user' => (string) $user->id,
            '--genre' => 'blank',
        ])->assertFailed();
    }

    public function test_it_fails_when_no_user_exists(): void
    {
        $this->artisan('app:seed-project', [
            '--genre' => 'blank',
            '--name' => 'Orphan',
        ])->assertFailed();
    }
}
