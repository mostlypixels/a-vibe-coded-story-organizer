<?php

namespace App\Services;

use App\Models\Project;

/**
 * Gives a new project its six top-level note categories.
 *
 * Call it from the project create and onboarding flows and the demo installer.
 * It is not a `Project::created` hook: a project is valid with no categories,
 * and an archive import must create only the archive's own categories.
 */
class StarterNoteCategories
{
    /** @var list<string> */
    public const NAMES = ['Planning', 'Research', 'Continuity', 'Plot threads', 'Publishing', 'Ideas'];

    public function createFor(Project $project): void
    {
        foreach (self::NAMES as $name) {
            $project->noteCategories()->firstOrCreate(['parent_id' => null, 'name' => $name]);
        }
    }
}
