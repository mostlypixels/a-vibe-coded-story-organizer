<?php

namespace Tests\Feature;

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
}
