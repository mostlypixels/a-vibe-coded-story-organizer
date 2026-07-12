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
        // doesn't hit the users.email UNIQUE constraint before the Melusine seeders (which
        // are themselves idempotent via firstOrCreate) are ever reached. The factory is kept
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

        // Three separate projects (one per language) so the epub export's per-project
        // language metadata can be exercised across distinct sample content.
        $this->call([
            MelusineSeederEn::class,
            MelusineSeederFr::class,
            MelusineSeederIt::class,
        ]);
    }
}
