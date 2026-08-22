<?php

namespace App\Console\Commands;

use App\Enums\Genre;
use App\Models\User;
use App\Services\SeedsGenreBundle;
use Illuminate\Console\Command;

/**
 * Thin wrapper over {@see SeedsGenreBundle}: the same seed path the onboarding
 * web flow uses, callable from the shell for local development and testing.
 */
class SeedProjectCommand extends Command
{
    protected $signature = 'app:seed-project
        {--user= : Email or ID of the owning user; defaults to the first user}
        {--genre=blank : One of the Genre enum values}
        {--name= : The new project name}';

    protected $description = 'Create a project and apply a genre bundle for a user';

    public function handle(SeedsGenreBundle $action): int
    {
        $user = $this->resolveUser();

        if ($user === null) {
            $this->error('No user found. Pass --user with an email or ID.');

            return self::FAILURE;
        }

        $genre = Genre::tryFrom((string) $this->option('genre'));

        if ($genre === null) {
            $allowed = implode(', ', array_map(fn (Genre $case): string => $case->value, Genre::cases()));
            $this->error("Unknown genre \"{$this->option('genre')}\". Allowed genres: {$allowed}.");

            return self::FAILURE;
        }

        $name = $this->option('name');

        if (! is_string($name) || trim($name) === '') {
            $this->error('--name is required.');

            return self::FAILURE;
        }

        $project = $action->seed($user, $genre, $name);

        $this->info("Created project \"{$project->name}\" (ID {$project->id}) for {$user->email}.");

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $identifier = $this->option('user');

        if ($identifier === null) {
            return User::query()->orderBy('id')->first();
        }

        if (is_numeric($identifier)) {
            return User::find((int) $identifier);
        }

        return User::where('email', $identifier)->first();
    }
}
