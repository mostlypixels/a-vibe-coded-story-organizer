<?php

namespace App\Support;

use Illuminate\Support\Str;

class PlotlineColors
{
    /**
     * 500 and 700 shades of 16 Tailwind color families.
     *
     * @var array<int, string>
     */
    public const PRESETS = [
        '#ef4444', '#b91c1c', // red
        '#f97316', '#c2410c', // orange
        '#eab308', '#a16207', // yellow
        '#84cc16', '#4d7c0f', // lime
        '#22c55e', '#15803d', // green
        '#10b981', '#047857', // emerald
        '#14b8a6', '#0f766e', // teal
        '#06b6d4', '#0e7490', // cyan
        '#0ea5e9', '#0369a1', // sky
        '#3b82f6', '#1d4ed8', // blue
        '#6366f1', '#4338ca', // indigo
        '#8b5cf6', '#6d28d9', // violet
        '#a855f7', '#7e22ce', // purple
        '#d946ef', '#a21caf', // fuchsia
        '#ec4899', '#be185d', // pink
        '#f43f5e', '#be123c', // rose
    ];

    /** One name for each pair in PRESETS, in the same order. */
    private const FAMILIES = [
        'Red', 'Orange', 'Yellow', 'Lime', 'Green', 'Emerald', 'Teal', 'Cyan',
        'Sky', 'Blue', 'Indigo', 'Violet', 'Purple', 'Fuchsia', 'Pink', 'Rose',
    ];

    /** The accessible name of a swatch. A screen reader otherwise reads the hex code. */
    public static function label(string $hex): string
    {
        $index = array_search($hex, self::PRESETS, true);

        if ($index === false) {
            return $hex;
        }

        $family = __(self::FAMILIES[intdiv($index, 2)]);

        return $index % 2 === 0 ? $family : __('Dark :color', ['color' => Str::lower($family)]);
    }
}
