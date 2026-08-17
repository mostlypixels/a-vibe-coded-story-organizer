<?php

namespace App\Support;

use App\Models\Book;
use App\Models\Project;
use Stringable;

/**
 * The document <title> of an authenticated page.
 *
 * Two shapes, one rule: inside a project the title leads with a name
 * ("Melusine - AVCSO"), everywhere else it is the bare app name. The name is
 * the route's book, through {@see Book::displayName()}, when the route has
 * one, else the route's project. `displayName()` falls back to the project's
 * own name for an unnamed book, so a project's sole book renders exactly the
 * title it did before the book layer existed. The browser tab is the only
 * place a writer juggling several projects can tell two windows apart, so
 * the leading name goes first — tabs truncate from the right.
 *
 * The app name itself comes from config (APP_NAME), never a literal.
 */
class PageTitle implements Stringable
{
    /** Separator between the leading name and the app name. */
    private const SEPARATOR = ' - ';

    public function __construct(
        private readonly ?Project $project,
        private readonly ?Book $book = null,
    ) {}

    public function __toString(): string
    {
        $appName = (string) config('app.name');
        $name = $this->book?->displayName() ?? $this->project?->name;

        return $name === null
            ? $appName
            : $name.self::SEPARATOR.$appName;
    }
}
