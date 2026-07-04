<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Guarded so a re-run of `php artisan db:seed` against a populated database
        // doesn't hit the users.email UNIQUE constraint before MelusineSeeder (which
        // is itself idempotent via firstOrCreate) is ever reached. The factory is kept
        // rather than a plain firstOrCreate because its defaults mark the email
        // verified — email_verified_at is not fillable, so firstOrCreate would
        // silently drop it and lock the admin out of the `verified` dashboard route.
        if (User::where('email', 'admin@example.com')->doesntExist()) {
            User::factory()->create([
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
            ]);
        }

        $this->call(MelusineSeeder::class);
    }
}
