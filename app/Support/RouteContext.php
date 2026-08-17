<?php

namespace App\Support;

use App\Models\Book;
use App\Models\Project;
use Illuminate\Http\Request;

/**
 * Resolves which project — and, for a manuscript route, which book — the
 * current request's route belongs to.
 *
 * Shallow child routes (e.g. /scenes/{scene}/edit) carry no {project} or
 * {book} route parameter, so this walks whatever model the route did bind
 * back up to its project and book. One walk answers both questions, because
 * resolving the book *is* the walk that resolves the project — a second copy
 * would drift the first time a route parameter is added. Extracted out of
 * ProjectNavigation so the nav and TrackActiveProject middleware agree on the
 * answer.
 *
 * A route outside the manuscript ({project}, {plotline}, {event},
 * {codexEntry}, {codexAttribute}) resolves a project and no book — the
 * timeline and codex stay project-wide, not book-scoped.
 */
final class RouteContext
{
    private function __construct(
        public readonly ?Project $project,
        public readonly ?Book $book,
    ) {}

    public static function resolve(Request $request): self
    {
        $book = $request->route('book')
            ?? $request->route('act')?->book
            ?? $request->route('chapter')?->act?->book
            ?? $request->route('scene')?->chapter?->act?->book;

        if ($book !== null) {
            return new self($book->project, $book);
        }

        $project = $request->route('project')
            ?? $request->route('plotline')?->project
            ?? $request->route('event')?->project
            ?? $request->route('codexEntry')?->project
            ?? $request->route('codexAttribute')?->project;

        return new self($project, null);
    }
}
