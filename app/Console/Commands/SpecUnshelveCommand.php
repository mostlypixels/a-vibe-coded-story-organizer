<?php

namespace App\Console\Commands;

use App\Services\SpecShelf;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use RuntimeException;

/**
 * Moves a shelved spec back to `.specs/draft/<name>/` (see .specs/README.md → Shelving).
 * The reason note written by `spec:shelve` is removed on the way out.
 */
class SpecUnshelveCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'spec:unshelve {name? : Kebab-case name of the shelved spec}';

    protected $description = 'Move a shelved spec back to .specs/draft/<name>/';

    public function handle(): int
    {
        $name = (string) $this->argument('name');

        try {
            $path = SpecShelf::unshelve($name);
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $this->info("Unshelved: .specs/$path");
        $this->line("Next step: /mp-expand-spec $name");

        return self::SUCCESS;
    }
}
