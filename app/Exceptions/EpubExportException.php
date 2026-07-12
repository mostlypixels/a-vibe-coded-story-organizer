<?php

namespace App\Exceptions;

use App\Services\EpubExporter;
use RuntimeException;

/**
 * Thrown by {@see EpubExporter::export()} when a project has nothing to
 * export — every Act/Chapter was filtered out because no Chapter has any Scenes.
 *
 * This is the ONE epub-export failure that is a **user input problem**, not a bug: the
 * author simply has no content yet. Task 06's controller catches it and turns it into a
 * redirect-back-with-error (a normal validation-style message), so it must stay distinct
 * from the well-formedness/schema failures in EpubExporter, which are generator bugs and
 * are thrown as plain RuntimeExceptions (let them 500 and be logged).
 */
class EpubExportException extends RuntimeException
{
    /**
     * The filtered act/chapter tree is empty — there is no content to package.
     */
    public static function nothingToExport(): self
    {
        return new self('This project has no scenes to export yet. Add at least one scene to a chapter before exporting an epub.');
    }
}
