<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by ProjectImporter::run() when another run holds a fresh claim on the
 * import. The message is safe to show to the writer.
 */
class ImportAlreadyRunningException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('This import is already running.'));
    }
}
