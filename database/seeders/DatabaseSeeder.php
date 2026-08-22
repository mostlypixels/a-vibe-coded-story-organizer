<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * `db:seed` creates the admin user only. The demo projects and the second user
 * come from `app:install-demo` and `app:install-test-fixtures` instead, so the
 * empty-app onboarding flow is never bypassed by a plain seed.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Guarded so a re-run of `php artisan db:seed` against a populated database
        // doesn't hit the users.email UNIQUE constraint. The factory is kept rather
        // than a plain firstOrCreate because its defaults mark the email verified —
        // email_verified_at is not fillable, so firstOrCreate would silently drop it
        // and lock the admin out of the `verified` dashboard route.
        if (User::where('email', 'admin@example.com')->doesntExist()) {
            User::factory()->create([
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
            ]);
        }
    }
}
