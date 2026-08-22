<?php

namespace App\Services;

use App\Models\User;
use Database\Seeders\MelusineSeederEn;
use Database\Seeders\MelusineSeederFr;
use Database\Seeders\MelusineSeederIt;
use Illuminate\Database\Eloquent\Model;

/**
 * Seeds the three Melusine demo projects for one user. The single entry point
 * behind both `app:install-demo` and the onboarding demo button, so the two
 * never drift.
 *
 * > [!WARNING]
 * > Unguards models (the seeders set `user_id`, which is not fillable) and
 * > turns model events off, the same way `db:seed` does — each seeder builds
 * > its own book by hand, so `Project::created` must not run.
 *
 * Idempotent: a second run for the same user is a no-op, since each seeder
 * guards by the user's existing projects.
 */
class InstallsDemoProjects
{
    public function install(User $user): void
    {
        Model::unguarded(fn () => Model::withoutEvents(function () use ($user): void {
            app(MelusineSeederEn::class)->forUser($user)->run();
            app(MelusineSeederFr::class)->forUser($user)->run();
            app(MelusineSeederIt::class)->forUser($user)->run();
        }));
    }
}
