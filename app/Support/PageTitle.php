<?php

namespace App\Support;

use App\Models\Project;
use Stringable;

/**
 * The document <title> of an authenticated page.
 *
 * Two shapes, one rule: inside a project the title leads with the project's
 * name ("Melusine - AVCSO"), everywhere else it is the bare app name. The
 * browser tab is the only place a writer juggling several projects can tell
 * two windows apart, so the project name goes first — tabs truncate from the
 * right.
 *
 * The app name itself comes from config (APP_NAME), never a literal.
 */
class PageTitle implements Stringable
{
    /** Separator between the project name and the app name. */
    private const SEPARATOR = ' - ';

    public function __construct(private readonly ?Project $project) {}

    public function __toString(): string
    {
        $appName = (string) config('app.name');

        return $this->project === null
            ? $appName
            : $this->project->name.self::SEPARATOR.$appName;
    }
}
