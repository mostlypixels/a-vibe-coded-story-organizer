<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Book;
use App\Support\ListJump;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Turns a Go-to request into a redirect, following {@see ResolvesIndexSorting}'s
 * shape: this trait resolves, the caller returns.
 *
 * A Go-to request always shows the whole book at the target group — it clears
 * search and filter and forces story order. `$groups` is already scoped to
 * the book, so it also doubles as the id's authorization boundary: an id
 * outside it is treated as absent, never as a foreign lookup.
 */
trait JumpsToListPosition
{
    /**
     * The redirect a Go-to request should answer with, or null when this is
     * not one.
     *
     * @param  string  $route  'books.scenes.index' | 'books.chapters.index'.
     * @param  Builder  $rows  The book's unfiltered row query.
     * @param  string  $column  'scenes.chapter_id' | 'chapters.act_id'.
     * @param  EloquentCollection  $groups  Story-ordered, from chaptersFor()/actsFor().
     * @param  string  $fragmentPrefix  'chapter' | 'act'.
     */
    protected function jumpRedirect(
        Request $request,
        string $route,
        Book $book,
        Builder $rows,
        string $column,
        EloquentCollection $groups,
        int $perPage,
        string $fragmentPrefix,
    ): ?RedirectResponse {
        if (! $request->filled('jump')) {
            return null;
        }

        $targetId = (int) $request->query($fragmentPrefix);

        // An id outside the book's own groups is treated as absent, not as a
        // foreign lookup — $groups is already book-scoped. Still a redirect,
        // never null, so `jump` cannot survive into a rendered page.
        if (! $groups->contains('id', $targetId)) {
            return redirect()->route($route, $book);
        }

        $page = ListJump::page($rows, $column, $groups->pluck('id')->all(), $targetId, $perPage);

        // Only page/highlight/sort/direction — search, filter and jump are
        // dropped by construction, not by subtraction.
        $url = route($route, [
            $book,
            'page' => $page,
            'highlight' => $targetId,
            'sort' => 'position',
            'direction' => 'asc',
        ]).'#'.$fragmentPrefix.'-'.$targetId;

        return redirect($url);
    }
}
