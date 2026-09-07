<?php

namespace App\Console\Commands;

use App\Services\SpecShelf;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use RuntimeException;

/**
 * Moves a draft to `.specs/shelved/<name>/` (see .specs/README.md → Shelving).
 *
 * The drafting queue is meant to read as work someone will pick up. A draft
 * that is real but not wanted for months buries the ones that are, so it goes
 * on the shelf instead of being deleted or annotated with a note nobody sorts by.
 */
class SpecShelveCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'spec:shelve
        {name? : Kebab-case name of the draft to shelve}
        {--reason= : Why it is shelved, written into the spec as a note}';

    protected $description = 'Move a draft spec to .specs/shelved/<name>/';

    public function handle(): int
    {
        try {
            $path = SpecShelf::shelve((string) $this->argument('name'), $this->option('reason'));
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $this->info("Shelved: .specs/$path");
        $this->line('Bring it back with: php artisan spec:unshelve '.$this->argument('name'));

        return self::SUCCESS;
    }
}
