<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\InstallsDemoProjects;
use Illuminate\Console\Command;

/**
 * Thin wrapper over {@see InstallsDemoProjects}: the same demo install path
 * the onboarding web flow uses, callable from the shell for local development
 * and testing.
 */
class InstallDemoCommand extends Command
{
    protected $signature = 'app:install-demo
        {--user= : Email or ID of the target user; defaults to the first user}';

    protected $description = 'Install the Melusine demo projects for a user';

    public function handle(InstallsDemoProjects $action): int
    {
        $user = $this->resolveUser();

        if ($user === null) {
            $this->error('No user found. Pass --user with an email or ID.');

            return self::FAILURE;
        }

        $action->install($user);

        $this->info("Installed the Melusine demo for {$user->email}.");

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
