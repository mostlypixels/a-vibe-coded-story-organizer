<?php

namespace App\Services\Import;

use App\Models\Act;
use App\Models\Project;

/**
 * What one `manuskript:import` run produced: the project and its single act
 * (always populated), and the counts/skip reasons that later import stages
 * fill in as they walk chapters, scenes and characters.
 *
 * The count properties default to zero and `$skipped` to an empty list so a
 * caller can print the report after any stage runs, whether or not later
 * stages ran at all.
 */
class ManuskriptImportResult
{
    public int $chapterCount = 0;

    public int $sceneCount = 0;

    public int $characterCount = 0;

    /** How many scene body blocks were replaced for disallowed raw HTML. */
    public int $disallowedContentCount = 0;

    /** @var array<int, string> human-readable reasons, one per skipped file */
    public array $skipped = [];

    public function __construct(
        public readonly Project $project,
        public readonly Act $act,
    ) {}
}
