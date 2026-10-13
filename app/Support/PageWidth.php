<?php

namespace App\Support;

/**
 * The single door from a user's stored `page_width` column to the share of
 * the window the page content fills.
 *
 * Same shape as PageSize: one integer, an allow-list in config. A step reaches
 * CSS only through css(), never from raw input.
 */
final class PageWidth
{
    /** @return list<int> */
    public static function steps(): array
    {
        return config('page_width.steps');
    }

    /** The stored column, or the configured default when null or off the allow-list. */
    public static function resolve(?int $stored): int
    {
        if ($stored !== null && in_array($stored, self::steps(), true)) {
            return $stored;
        }

        return config('page_width.default');
    }

    /**
     * Every step keyed to its CSS value, such as `[70 => '70%']`.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return array_combine(self::steps(), array_map(self::css(...), self::steps()));
    }

    /** The CSS value for a step, such as `70%`. */
    public static function css(int $step): string
    {
        return $step.'%';
    }
}
