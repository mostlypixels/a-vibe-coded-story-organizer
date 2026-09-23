<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Checks the query values of an index page before any query or view reads them.
 *
 * A value sent as `?search[]=x` is an array. Without this check, it reaches
 * `where()` or a Blade `value` attribute and causes a server error.
 */
trait ValidatesIndexFilters
{
    /** @param  list<string>  $idFilters  The page's id dropdown filters, for example `chapter`. */
    protected function validateIndexFilters(Request $request, array $idFilters = []): void
    {
        $request->validate([
            'search' => ['nullable', 'string'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'string'],
            ...array_fill_keys($idFilters, ['nullable', 'integer']),
        ]);
    }
}
