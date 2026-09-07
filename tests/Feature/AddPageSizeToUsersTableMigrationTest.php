<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Run the page-size migration against rows that predate its column. */
class AddPageSizeToUsersTableMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = include database_path('migrations/2026_09_07_000000_add_page_size_to_users_table.php');

        return $migration;
    }

    public function test_the_column_exists_and_is_nullable(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'page_size'));

        $user = User::factory()->create(['page_size' => null]);

        $this->assertNull($user->fresh()->page_size);
    }

    public function test_a_user_created_before_the_migration_has_a_null_page_size_after_it(): void
    {
        $migration = $this->migration();
        $migration->down();

        $user = User::factory()->create();

        $migration->up();

        $this->assertNull($user->fresh()->page_size);
    }

    public function test_down_drops_the_column_and_up_can_run_again_afterwards(): void
    {
        $migration = $this->migration();

        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'page_size'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('users', 'page_size'));
    }

    public function test_the_column_stores_an_allowed_size(): void
    {
        $user = User::factory()->create(['page_size' => 250]);

        $this->assertSame(250, $user->fresh()->page_size);
    }
}
