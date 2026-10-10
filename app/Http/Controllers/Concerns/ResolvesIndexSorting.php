<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Resolves the `?sort=` / `?direction=` query pair an index page is ordered by.
 *
 * Every entity index accepts the same two parameters and applies the same rule to
 * them: an unrecognised column falls back to the page's default, and anything
 * other than the literal `desc` means ascending.
 *
 * The allow-list is the security boundary here: `$sort` reaches `orderBy()` as a
 * column name, so it must never be whatever the query string happened to say.
 * Keeping that check in one place is the point of this trait — a new index that
 * forgets it would be an injection hole, and there is now nothing to forget.
 *
 * This is not a query scope. The trait only resolves
 * the two values — the caller still writes its own `orderBy()`, because which
 * secondary sort a page needs (chapters group by `act_id` first, scenes by
 * `chapter_id`) is the page's business.
 */
trait ResolvesIndexSorting
{
    /**
     * The `[sort, direction]` pair for an index page.
     *
     * @param  list<string>  $sortable  The columns this page allows sorting by.
     * @param  string  $default  The column used when `?sort=` is absent or unrecognised.
     * @param  string  $defaultDirection  Used with the default column when `?direction=` is absent.
     * @return array{0: string, 1: string} `direction` is always exactly `asc` or `desc`.
     */
    protected function resolveSorting(Request $request, array $sortable, string $default, string $defaultDirection = 'asc'): array
    {
        // Strict comparison: query values arrive as strings or null, and a loose
        // in_array() against a string list has surprising edge cases.
        $sort = in_array($request->query('sort'), $sortable, true)
            ? (string) $request->query('sort')
            : $default;

        $direction = $request->query('direction');

        if ($direction === null) {
            return [$sort, $sort === $default ? $defaultDirection : 'asc'];
        }

        return [$sort, $direction === 'desc' ? 'desc' : 'asc'];
    }
}
