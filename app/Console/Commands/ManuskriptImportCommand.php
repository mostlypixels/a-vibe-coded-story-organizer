<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\User;
use App\Services\Import\ManuskriptImporter;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Imports an exploded Manuskript project directory as a new {@see Project}.
 *
 * Argument parsing, user resolution and the printed report only — every
 * domain rule (the gate, the graph it writes, the transaction) lives in
 * {@see ManuskriptImporter}, following {@see WordCountDemoHistoryCommand}'s
 * precedent of a thin, validating command.
 *
 * > [!WARNING]
 * > Branch-only: no route, no policy, and so no authorization check here —
 * > a console command has no authenticated user to authorize against.
 */
class ManuskriptImportCommand extends Command
{
    protected $signature = 'manuskript:import
        {path       : Path to the exploded project directory (the one holding the MANUSKRIPT file)}
        {--user=    : Owner of the imported project — a user ID or email}
        {--name=    : Override the project name (default: infos.txt Title)}
        {--act=     : Name of the single act holding every chapter (default: "Act 1")}';

    protected $description = 'Import an exploded Manuskript project directory as a new project';

    public function handle(ManuskriptImporter $importer): int
    {
        $user = $this->resolveUser();

        if ($user === null) {
            return self::FAILURE;
        }

        try {
            $result = $importer->import(
                (string) $this->argument('path'),
                $user,
                $this->option('name'),
                $this->option('act'),
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported \"{$result->project->name}\" for {$user->email}.");
        $this->line("Act: {$result->act->name}");
        $this->line("Chapters: {$result->chapterCount}, scenes: {$result->sceneCount}, characters: {$result->characterCount}");

        if ($result->disallowedContentCount > 0) {
            $this->warn("Disallowed HTML removed from {$result->disallowedContentCount} scene content block(s).");
        }

        if ($result->skipped !== []) {
            $this->warn('Skipped:');
            foreach ($result->skipped as $reason) {
                $this->line("  - {$reason}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * `--user` by ID or email; omitted only when the install has exactly one
     * user, in which case that user is the default. Absent, ambiguous, or
     * unknown is a printed error and a null return — never a prompt.
     */
    private function resolveUser(): ?User
    {
        $option = $this->option('user');

        if ($option === null) {
            if (User::query()->count() !== 1) {
                $this->error('--user is required unless the install has exactly one user.');

                return null;
            }

            return User::query()->firstOrFail();
        }

        $user = is_numeric($option)
            ? User::find((int) $option)
            : User::where('email', $option)->first();

        if ($user === null) {
            $this->error("No user found for \"{$option}\".");

            return null;
        }

        return $user;
    }
}
