<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | The fallback for a `null` column and for a stored slug that no longer
    | matches a configured entry. Every default here must be a key of its own
    | list below — FontConfigTest enforces it.
    |
    | Not database rows for the same reason as `config/themes.php`: this is
    | self-hosted, so adding or renaming a font is a file edit.
    |
    */

    'default_ui' => 'inter',
    'default_manuscript' => 'inter',
    'default_ui_scale' => 'normal',
    'default_manuscript_scale' => 'same',
    'default_leading' => 'normal',

    /*
    |--------------------------------------------------------------------------
    | Families
    |--------------------------------------------------------------------------
    |
    | The same list backs both the UI face and the manuscript face — a user
    | picks two slugs into one list, not two lists.
    |
    | `stack` is the full CSS font-family value, already quoted, rendered
    | verbatim. It is authored here, never assembled from input, which is why
    | no sanitization runs on it — only the slug is validated.
    |
    | `bundled` marks whether the family ships as a checked-in woff2 with an
    | `@font-face` rule (fetched by `scripts/fetch-fonts.sh`) or resolves to a
    | font the reader's system already has. `note` is the reason the family is
    | on the list at all; the picker shows it next to the family name.
    |
    */

    'families' => [

        'inter' => [
            'name' => 'Inter',
            'stack' => 'Inter, ui-sans-serif, system-ui, sans-serif',
            'bundled' => true,
            'note' => 'Familiar sans; assumes no visual impairment.',
        ],

        'atkinson' => [
            'name' => 'Atkinson Hyperlegible',
            // The bundled woff2/@font-face family name is "…Next" (app.css),
            // which is the CSS font-family this stack must reference; only
            // the display name above drops the suffix.
            'stack' => "'Atkinson Hyperlegible Next', ui-sans-serif, system-ui, sans-serif",
            'bundled' => true,
            'note' => 'Designed for readers with low vision.',
        ],

        'lexend' => [
            'name' => 'Lexend',
            'stack' => 'Lexend, ui-sans-serif, system-ui, sans-serif',
            'bundled' => true,
            'note' => 'Tuned to reduce reading effort, including for dyslexia.',
        ],

        'literata' => [
            'name' => 'Literata',
            'stack' => 'Literata, ui-serif, Georgia, serif',
            'bundled' => true,
            'note' => 'A book-style serif for long-form manuscript reading.',
        ],

        'source-serif-4' => [
            'name' => 'Source Serif 4',
            'stack' => "'Source Serif 4', ui-serif, Georgia, serif",
            'bundled' => true,
            'note' => 'A second serif with a plainer, more traditional shape.',
        ],

        'arial' => [
            'name' => 'Arial',
            'stack' => 'Arial, Helvetica, sans-serif',
            'bundled' => false,
            'note' => 'Whatever Arial the reader already has installed.',
        ],

        'verdana' => [
            'name' => 'Verdana',
            'stack' => 'Verdana, Geneva, sans-serif',
            'bundled' => false,
            'note' => 'Wide letterforms some readers find easier to scan.',
        ],

        'georgia' => [
            'name' => 'Georgia',
            'stack' => 'Georgia, Times, serif',
            'bundled' => false,
            'note' => 'A widely installed serif, no download required.',
        ],

        'system' => [
            'name' => 'System font',
            'stack' => 'system-ui, sans-serif',
            'bundled' => false,
            'note' => "Whatever the reader's device already uses everywhere else.",
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | UI scale
    |--------------------------------------------------------------------------
    |
    | Percentages set on `:root { font-size }`. Everything sized in `rem`
    | scales with it — the whole chrome, not only text.
    |
    */

    'ui_scales' => [
        'normal' => '100%',
        'large' => '112.5%',
        'larger' => '125%',
    ],

    /*
    |--------------------------------------------------------------------------
    | Manuscript scale
    |--------------------------------------------------------------------------
    |
    | Percentages applied on `.prose`, relative to the UI scale above — the two
    | compose rather than one overriding the other. Labelled *same / larger /
    | largest* rather than absolute sizes, because "normal" would be ambiguous
    | once `ui_scale` has already changed the root.
    |
    */

    'manuscript_scales' => [
        'same' => '100%',
        'larger' => '112.5%',
        'largest' => '125%',
    ],

    /*
    |--------------------------------------------------------------------------
    | Line height
    |--------------------------------------------------------------------------
    |
    | Unitless `line-height` values on `.prose`.
    |
    */

    'leading' => [
        'tight' => '1.4',
        'normal' => '1.6',
        'loose' => '1.9',
    ],

];
