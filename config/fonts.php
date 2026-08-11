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
    'default_ui_leading' => 'default',
    'default_leading' => 'default',

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
    | on the list at all. The picker does not print it — the cards stay short —
    | except as the label of the eye icon described below.
    |
    | `accessible` marks a family drawn for impaired reading. The picker gives it
    | an eye icon, and `note` becomes that icon's label — so an accessible family
    | must say in its note what it is designed for.
    |
    */

    'families' => [

        'inter' => [
            'name' => 'Inter',
            'stack' => 'Inter, ui-sans-serif, system-ui, sans-serif',
            'bundled' => true,
            'accessible' => false,
            'note' => 'Familiar sans; assumes no visual impairment.',
        ],

        'atkinson' => [
            'name' => 'Atkinson Hyperlegible',
            // The bundled woff2/@font-face family name is "…Next" (app.css),
            // which is the CSS font-family this stack must reference; only
            // the display name above drops the suffix.
            'stack' => "'Atkinson Hyperlegible Next', ui-sans-serif, system-ui, sans-serif",
            'bundled' => true,
            'accessible' => true,
            'note' => 'Designed for readers with low vision.',
        ],

        'lexend' => [
            'name' => 'Lexend',
            'stack' => 'Lexend, ui-sans-serif, system-ui, sans-serif',
            'bundled' => true,
            'accessible' => true,
            'note' => 'Tuned to reduce reading effort, including for dyslexia.',
        ],

        'literata' => [
            'name' => 'Literata',
            'stack' => 'Literata, ui-serif, Georgia, serif',
            'bundled' => true,
            'accessible' => false,
            'note' => 'A book-style serif for long-form manuscript reading.',
        ],

        'source-serif-4' => [
            'name' => 'Source Serif 4',
            'stack' => "'Source Serif 4', ui-serif, Georgia, serif",
            'bundled' => true,
            'accessible' => false,
            'note' => 'A second serif with a plainer, more traditional shape.',
        ],

        'jetbrains-mono' => [
            'name' => 'JetBrains Mono',
            'stack' => "'JetBrains Mono', ui-monospace, monospace",
            'bundled' => true,
            'accessible' => false,
            'note' => 'Fixed width; every character occupies the same space.',
        ],

        'arial' => [
            'name' => 'Arial',
            'stack' => 'Arial, Helvetica, sans-serif',
            'bundled' => false,
            'accessible' => false,
            'note' => 'Whatever Arial the reader already has installed.',
        ],

        'verdana' => [
            'name' => 'Verdana',
            'stack' => 'Verdana, Geneva, sans-serif',
            'bundled' => false,
            'accessible' => false,
            'note' => 'Wide letterforms some readers find easier to scan.',
        ],

        'georgia' => [
            'name' => 'Georgia',
            'stack' => 'Georgia, Times, serif',
            'bundled' => false,
            'accessible' => false,
            'note' => 'A widely installed serif, no download required.',
        ],

        'system' => [
            'name' => 'System font',
            'stack' => 'system-ui, sans-serif',
            'bundled' => false,
            'accessible' => false,
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
    | Five steps of 2px against the browser's 16px root, which the picker prints
    | as px. `normal` is the middle step on purpose: the track then reads as
    | smaller-to-larger around the default.
    |
    | 75% is 12px, below the comfortable floor for body text. It stays reachable
    | for readers who want density on a high-DPI screen, but the range is not
    | centred there.
    |
    */

    'ui_scales' => [
        'smallest' => '75%',
        'small' => '87.5%',
        'normal' => '100%',
        'large' => '112.5%',
        'largest' => '125%',
    ],

    /*
    |--------------------------------------------------------------------------
    | Manuscript scale
    |--------------------------------------------------------------------------
    |
    | Percentages applied on `.prose`, relative to the UI scale above — the two
    | compose rather than one overriding the other. The picker prints these as
    | multipliers, never px: the resulting size depends on `ui_scales`, so a px
    | label here would go stale the moment the interface size changes.
    |
    | The list starts at parity and only grows. A manuscript smaller than the
    | chrome around it has no reader.
    |
    */

    'manuscript_scales' => [
        'same' => '100%',
        'bigger' => '115%',
        'large' => '130%',
        'larger' => '145%',
        'largest' => '160%',
    ],

    /*
    |--------------------------------------------------------------------------
    | Line height
    |--------------------------------------------------------------------------
    |
    | **Multipliers of each surface's default line height**, not line heights
    | themselves: `1` leaves the spacing alone and `2` doubles it. The picker
    | prints them as `1×` … `2×`.
    |
    | The multiplier cannot be applied in CSS, because a rule cannot read the
    | line height it is overriding. So the base sits in `leading_bases` below and
    | FontStyleBlock multiplies the two before rendering.
    |
    | The floor is 1 — the default. Tighter than default is not offered: below a
    | multiplier of 1 the descenders of one line start to collide with the
    | ascenders of the next, which is not a setting but a defect.
    |
    */

    'leading' => [
        'default' => '1',
        'roomier' => '1.25',
        'roomy' => '1.5',
        'airy' => '1.75',
        'double' => '2',
    ],

    /*
    |--------------------------------------------------------------------------
    | Line height bases
    |--------------------------------------------------------------------------
    |
    | What a `leading` multiplier of 1 means per surface, so that step really is
    | "unchanged":
    |
    | - `manuscript` is Tailwind Typography's own `.prose` line height.
    | - `ui` is Tailwind's `text-base` line height, the middle of the `text-*`
    |   scale the chrome uses. One value replaces all of them, so the smallest
    |   and largest utilities shift slightly at a multiplier of 1.
    |
    | Change these only against the framework's own numbers — they are a mirror
    | of Tailwind's defaults, not a taste setting.
    |
    */

    'leading_bases' => [
        'ui' => 1.5,
        'manuscript' => 1.75,
    ],

];
