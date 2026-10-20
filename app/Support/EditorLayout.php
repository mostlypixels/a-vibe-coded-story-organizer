<?php

namespace App\Support;

/**
 * The single door from a user's stored `editor_layout` column to the panel
 * widths and collapse state of the Write page.
 *
 * Same role as PageWidth. The stored JSON is never trusted: resolve() fills
 * defaults, drops unknown keys and clamps widths.
 */
final class EditorLayout
{
    private const PANELS = ['left', 'right'];

    private const SECTIONS = ['codex', 'notes', 'recent'];

    /**
     * The full shape, as `config('editor.layout')` lays it out.
     *
     * @param  array<mixed>|null  $stored
     * @return array{left: array{width: int, collapsed: bool}, right: array{width: int, collapsed: bool}, sections: array{codex: bool, notes: bool, recent: bool}}
     */
    public static function resolve(?array $stored): array
    {
        $defaults = config('editor.layout');
        $layout = ['left' => [], 'right' => [], 'sections' => []];

        foreach (self::PANELS as $panel) {
            $value = $stored[$panel] ?? null;
            $value = is_array($value) ? $value : [];

            $layout[$panel] = [
                'width' => self::clampWidth($value['width'] ?? null, $defaults[$panel]['width']),
                'collapsed' => is_bool($value['collapsed'] ?? null) ? $value['collapsed'] : $defaults[$panel]['collapsed'],
            ];
        }

        $sections = $stored['sections'] ?? null;
        $sections = is_array($sections) ? $sections : [];

        foreach (self::SECTIONS as $section) {
            $layout['sections'][$section] = is_bool($sections[$section] ?? null)
                ? $sections[$section]
                : $defaults['sections'][$section];
        }

        return $layout;
    }

    /**
     * Validation rules for the full shape. A width outside the limits fails.
     *
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        $min = config('editor.min_width');
        $max = config('editor.max_width');
        $rules = [];

        foreach (self::PANELS as $panel) {
            $rules[$panel] = ['required', 'array'];
            $rules["$panel.width"] = ['required', 'integer', "min:$min", "max:$max"];
            $rules["$panel.collapsed"] = ['required', 'boolean'];
        }

        $rules['sections'] = ['required', 'array'];

        foreach (self::SECTIONS as $section) {
            $rules["sections.$section"] = ['required', 'boolean'];
        }

        return $rules;
    }

    /** A stored width may be too wide for today's limits, so it is clamped, not rejected. */
    private static function clampWidth(mixed $width, int $default): int
    {
        if (! is_int($width)) {
            return $default;
        }

        return max(config('editor.min_width'), min(config('editor.max_width'), $width));
    }
}
