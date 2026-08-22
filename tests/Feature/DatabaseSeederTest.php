<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `db:seed` creates the admin user only. The demo projects and the second user
 * are the job of `app:install-demo` and `app:install-test-fixtures`; their
 * regression coverage lives in the matching command tests.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_creates_the_admin_user_and_can_run_twice(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(1, User::where('email', 'admin@example.com')->count());
    }

    public function test_the_seeder_creates_no_demo_projects(): void
    {
        $this->seed();

        $this->assertSame(0, Project::count());
    }
}
