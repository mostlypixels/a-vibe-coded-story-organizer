<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_can_run_twice_without_failing(): void
    {
        // A second `db:seed` against a populated database used to abort on the
        // users.email UNIQUE constraint before MelusineSeeder was ever reached.
        $this->seed();
        $this->seed();

        $this->assertSame(1, User::where('email', 'admin@example.com')->count());
    }

    public function test_the_seeder_does_not_duplicate_the_demo_projects_on_a_second_run(): void
    {
        // Each MelusineSeeder{En,Fr,It} used to `Project::create()` unconditionally,
        // so a second `db:seed` (or `make seed` run twice) silently doubled every
        // demo project instead of no-op'ing.
        $this->seed();
        $this->seed();

        $this->assertSame(3, Project::count());
        $this->assertSame(1, Project::where('name', 'The Roman of Melusine')->count());
        $this->assertSame(1, Project::where('name', 'Le Roman de Mélusine')->count());
        $this->assertSame(1, Project::where('name', 'Il Romanzo di Melusina')->count());
    }
}
