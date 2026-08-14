<?php

namespace App\Exceptions;

use App\Console\Commands\ManuskriptImportCommand;
use App\Services\Import\ManuskriptImporter;
use RuntimeException;

/**
 * Thrown by {@see ManuskriptImporter} when the source
 * directory cannot be imported. The message is user-safe by construction —
 * {@see ManuskriptImportCommand} prints it directly and
 * returns FAILURE.
 */
class ManuskriptImportException extends RuntimeException
{
    /**
     * `$path` has no `MANUSKRIPT` marker file or no `outline/` directory —
     * the two things every exploded Manuskript project has.
     */
    public static function notAManuskriptProject(string $path): self
    {
        return new self("\"{$path}\" does not look like an exploded Manuskript project (missing the MANUSKRIPT marker or the outline/ directory).");
    }

    /**
     * The project has no name: `infos.txt` has no `Title:` and `--name` was
     * not given.
     */
    public static function missingProjectName(): self
    {
        return new self('The project has no name: infos.txt has no "Title:" and --name was not given.');
    }
}
