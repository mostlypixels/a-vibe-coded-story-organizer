<?php

namespace App\Support;

/**
 * The single door from a user's stored `page_size` column to the row count an
 * entity index paginates by.
 *
 * Not a readonly value object like ThemePreset or FontChoice — those resolve
 * many fields together; this resolves one integer, and wrapping it would only
 * make callers write `->size`. Callers write `PageSize::resolve($request->user()?->page_size)`
 * inline; the allow-list lives here, so there is nothing for a caller to
 * forget.
 */
final class PageSize
{
    /** @return list<int> */
    public static function sizes(): array
    {
        return config('pagination.sizes');
    }

    /** The stored column, or the configured default when null or off the allow-list. */
    public static function resolve(?int $stored): int
    {
        if ($stored !== null && in_array($stored, self::sizes(), true)) {
            return $stored;
        }

        return config('pagination.default');
    }
}
