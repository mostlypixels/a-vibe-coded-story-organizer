<?php

namespace Tests\Feature\Console;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallTestFixturesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_demo_and_the_second_user(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);

        $this->artisan('app:install-test-fixtures', ['--user' => 'admin@example.com'])
            ->assertSuccessful();

        $this->assertSame(3, Project::where('user_id', $user->id)->count());

        $writer = User::where('email', 'writer@example.com')->first();
        $this->assertNotNull($writer);
        $this->assertTrue($writer->projects()->where('name', 'Lorem ipsum')->exists());
    }

    public function test_a_second_run_is_a_no_op(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);

        $this->artisan('app:install-test-fixtures', ['--user' => 'admin@example.com'])->assertSuccessful();
        $this->artisan('app:install-test-fixtures', ['--user' => 'admin@example.com'])->assertSuccessful();

        $this->assertSame(3, Project::where('user_id', $user->id)->count());

        $writer = User::where('email', 'writer@example.com')->firstOrFail();
        $this->assertSame(1, $writer->projects()->count());
    }

    /**
     * The demo data needs an owner other than the demo owner, so a non-owner 403
     * can be seen by hand in the app. The `Lorem ipsum` project belongs to the
     * second user, not the Melusine owner, and carries its own single book and act.
     */
    public function test_the_lorem_ipsum_project_has_a_distinct_second_owner(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);

        $this->artisan('app:install-test-fixtures', ['--user' => 'admin@example.com'])->assertSuccessful();

        $writer = User::where('email', 'writer@example.com')->firstOrFail();
        $project = Project::where('name', 'Lorem ipsum')->firstOrFail();

        $this->assertSame($writer->id, $project->user_id);
        $this->assertNotSame($writer->id, Project::where('name', 'The Roman of Melusine')->firstOrFail()->user_id);
        $this->assertSame(1, $project->acts()->count());
        $this->assertSame(1, $project->books()->count());
    }

    public function test_it_fails_when_no_demo_owner_exists(): void
    {
        $this->artisan('app:install-test-fixtures')->assertFailed();
    }
}
