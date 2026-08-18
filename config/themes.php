<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default preset
    |--------------------------------------------------------------------------
    |
    | Used for guests and users who did not select a preset. Must be a `presets` key.
    |
    */

    'default' => 'low-glare-dark',

    /*
    |--------------------------------------------------------------------------
    | Contrast ceiling
    |--------------------------------------------------------------------------
    |
    | Tests use this authoring limit. Rendering does not. A preset can override it.
    | WCAG floors live in App\Support\ColorContrast. `no-halation` is the deliberate
    | exception.
    |
    */

    'contrast' => [
        'default_ceiling' => 15.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Presets
    |--------------------------------------------------------------------------
    |
    | ThemePreset reads these final values. ThemePresetTest requires every token in
    | ThemeTokens::ALL and rejects unknown tokens. Generate ramps with
    | `php artisan theme:ramp`, then paste the values and source anchors here.
    | The picker translates `name` because config loads before the locale.
    |
    */

    'presets' => [

        /* Daylight keeps the original mixed hex and Tailwind OKLCH palette. Its navy
         * pressed button needs the 18.0 ceiling. */
        'daylight' => [
            'name' => 'Daylight',
            'contrast_ceiling' => 18.0,
            'tokens' => [
                'surface' => 'oklch(96.7% 0.003 264.542)',        // gray-100
                'surface-raised' => '#ffffff',                    // white
                'surface-sunken' => 'oklch(98.5% 0.002 247.839)', // gray-50
                'surface-overlay' => '#ffffff',                   // white

                'scrim' => 'oklch(55.1% 0.027 264.364)',          // gray-500

                'content' => '#023047',                           // navy-900

                'content-muted' => 'oklch(44.6% 0.03 256.802)',   // gray-600; was gray-500 (4.39)
                'content-subtle' => 'oklch(53% 0.027 264.364)',   // gray-500 darkened; was gray-400 (2.36)

                'border' => 'oklch(92.8% 0.006 264.531)',         // gray-200

                // Input boundaries must meet WCAG 1.4.11.
                'border-strong' => 'oklch(62% 0.024 261.325)',    // was gray-300 (1.34)

                // One focus color must contrast with the page and navigation band.
                'focus' => '#1e93af',                             // was ocean-500 (2.86)

                'primary' => '#023047',                           // navy-900
                'primary-content' => '#ffffff',                   // white
                'primary-hover' => '#033a4d',                     // navy-800
                'primary-active' => '#011f2f',                    // navy-950
                'link' => '#18697e',                              // ocean-700; was ocean-600 (4.15)
                'link-hover' => '#185767',                        // ocean-800

                'accent' => '#1b809a',                            // ocean-600; was fuchsia-500
                'accent-content' => '#185767',                    // ocean-800
                'accent-surface' => '#cfeaf2',                    // ocean-100

                'neutral' => 'oklch(96.7% 0.003 264.542)',        // gray-100
                'neutral-content' => 'oklch(37.3% 0.034 259.733)', // gray-700

                'highlight' => '#ffe494',                         // sun-200
                'highlight-content' => '#023047',                 // navy-900
                'table-header' => '#ffc933',                      // sun-400
                'table-header-content' => '#023047',              // navy-900

                'nav' => '#011f2f',                               // navy-950
                'nav-content' => '#ddeff7',                        // aqua-100
                'nav-content-muted' => '#8ecae6',                 // aqua-300
                'nav-raised' => '#123c49',                        // ocean-900 darkened; see `focus`

                'danger' => 'oklch(57.7% 0.245 27.325)',          // red-600
                'danger-content' => '#ffffff',                    // white
                'danger-surface' => 'oklch(97.1% 0.013 17.38)',   // red-50
                'danger-surface-content' => 'oklch(44.4% 0.177 26.899)', // red-800
                'success' => 'oklch(52.7% 0.154 150.069)',        // green-700; was green-600 (3.29)
                'success-content' => '#ffffff',                   // white
                'success-surface' => 'oklch(98.2% 0.018 155.826)', // green-50
                'success-surface-content' => 'oklch(44.8% 0.119 151.328)', // green-800
                'warning' => 'oklch(79.5% 0.184 86.047)',         // yellow-500

                // Yellow needs dark text to meet the contrast floor.
                'warning-content' => '#023047',                   // navy-900; was white (1.92)
                'warning-surface' => 'oklch(98.7% 0.026 102.212)', // yellow-50
                'warning-surface-content' => 'oklch(47.6% 0.114 61.907)', // yellow-800
                'info' => 'oklch(54.6% 0.245 262.881)',           // blue-600
                'info-content' => '#ffffff',                      // white
                'info-surface' => 'oklch(97% 0.014 254.604)',     // blue-50
                'info-surface-content' => 'oklch(42.4% 0.199 265.638)', // blue-800
            ],
        ],

        /*
         * Dusk is a dim light theme with a 12.0 contrast ceiling.
         * Generated by `php artisan theme:ramp` from these anchors:
         *
         *   neutral  oklch(0.6 0.012 255)                 surfaces, content, lines
         *   ocean    #219ebc          --max-chroma=0.14   primary, links, focus, accent
         *   danger   oklch(0.6 0.2 27)  --max-chroma=0.14
         *   success  oklch(0.6 0.2 150) --max-chroma=0.14
         *   warning  oklch(0.6 0.2 86)  --max-chroma=0.14  (also highlight / table header)
         *   info     oklch(0.6 0.2 263) --max-chroma=0.14
         * Surfaces use half-steps so text meets its floor and ceiling at each elevation.
         */
        'dusk' => [
            'name' => 'Dusk',
            'contrast_ceiling' => 12.0,
            'tokens' => [
                // Elevation rises with lightness.
                'surface' => 'oklch(0.888 0.012 255)',            // neutral-100
                'surface-raised' => 'oklch(0.922 0.012 255)',
                'surface-sunken' => 'oklch(0.847 0.012 255)',
                'surface-overlay' => 'oklch(0.955 0.012 255)',

                'scrim' => 'oklch(0.314 0.012 255)',              // neutral-800

                'content' => 'oklch(0.314 0.012 255)',            // neutral-800
                'content-muted' => 'oklch(0.396 0.012 255)',      // neutral-700
                'content-subtle' => 'oklch(0.44 0.012 255)',

                'border' => 'oklch(0.724 0.012 255)',             // neutral-300
                'border-strong' => 'oklch(0.56 0.012 255)',       // neutral-500
                'focus' => 'oklch(0.56 0.1013 219)',              // ocean-500

                'primary' => 'oklch(0.478 0.0864 219)',           // ocean-600
                'primary-content' => 'oklch(0.97 0.012 255)',     // neutral-50
                'primary-hover' => 'oklch(0.396 0.0716 219)',     // ocean-700
                'primary-active' => 'oklch(0.314 0.0568 219)',    // ocean-800
                'link' => 'oklch(0.396 0.0716 219)',              // ocean-700
                'link-hover' => 'oklch(0.314 0.0568 219)',        // ocean-800

                'accent' => 'oklch(0.519 0.094 219)',
                'accent-content' => 'oklch(0.396 0.0716 219)',    // ocean-700
                'accent-surface' => 'oklch(0.806 0.1101 219)',    // ocean-200
                'neutral' => 'oklch(0.806 0.012 255)',            // neutral-200
                'neutral-content' => 'oklch(0.314 0.012 255)',    // neutral-800

                'highlight' => 'oklch(0.806 0.14 86)',            // warning-200
                'highlight-content' => 'oklch(0.232 0.012 255)',  // neutral-900
                'table-header' => 'oklch(0.724 0.14 86)',         // warning-300
                'table-header-content' => 'oklch(0.232 0.012 255)', // neutral-900

                // The dark band lets `focus` contrast with navigation and the page.
                'nav' => 'oklch(0.15 0.0271 219)',                // ocean-950
                'nav-content' => 'oklch(0.806 0.012 255)',        // neutral-200
                'nav-content-muted' => 'oklch(0.642 0.1101 219)', // ocean-400
                'nav-raised' => 'oklch(0.232 0.042 219)',         // ocean-900

                // Yellow uses dark text; other solid statuses use light text.
                'danger' => 'oklch(0.478 0.14 27)',               // danger-600
                'danger-content' => 'oklch(0.97 0.012 255)',      // neutral-50
                'danger-surface' => 'oklch(0.806 0.1104 27)',     // danger-200
                'danger-surface-content' => 'oklch(0.396 0.14 27)', // danger-700
                'success' => 'oklch(0.478 0.1316 150)',           // success-600
                'success-content' => 'oklch(0.97 0.012 255)',     // neutral-50
                'success-surface' => 'oklch(0.806 0.14 150)',     // success-200
                'success-surface-content' => 'oklch(0.396 0.1091 150)', // success-700
                'warning' => 'oklch(0.724 0.14 86)',              // warning-300
                'warning-content' => 'oklch(0.232 0.012 255)',    // neutral-900
                'warning-surface' => 'oklch(0.806 0.14 86)',      // warning-200
                'warning-surface-content' => 'oklch(0.396 0.0811 86)', // warning-700
                'info' => 'oklch(0.478 0.14 263)',                // info-600
                'info-content' => 'oklch(0.97 0.012 255)',        // neutral-50
                'info-surface' => 'oklch(0.806 0.0971 263)',      // info-200
                'info-surface-content' => 'oklch(0.396 0.14 263)', // info-700
            ],
        ],

        /*
         * Low-glare dark limits halation with a 10.0 ceiling and lower accent chroma.
         * Generated by `php artisan theme:ramp` from these anchors:
         *
         *   neutral  oklch(0.6 0.018 250)                 surfaces, content, lines
         *   ocean    #219ebc          --max-chroma=0.12   primary, links, focus, accent
         *   danger   oklch(0.6 0.2 27)  --max-chroma=0.12
         *   success  oklch(0.6 0.2 150) --max-chroma=0.12
         *   warning  oklch(0.6 0.2 86)  --max-chroma=0.12
         *   info     oklch(0.6 0.2 263) --max-chroma=0.12
         * Surfaces and content use half-steps to stay within the contrast band.
         */
        'low-glare-dark' => [
            'name' => 'Low-glare dark',
            'contrast_ceiling' => 10.0,
            'tokens' => [
                // Elevation rises with lightness.
                'surface' => 'oklch(0.207 0.018 250)',
                'surface-raised' => 'oklch(0.244 0.018 250)',
                'surface-sunken' => 'oklch(0.17 0.018 250)',
                'surface-overlay' => 'oklch(0.281 0.018 250)',

                // A near-black scrim dims dark surfaces instead of washing them out.
                'scrim' => 'oklch(0.05 0 0)',

                // Text stays below white to limit halation.
                'content' => 'oklch(0.784 0.018 250)',
                'content-muted' => 'oklch(0.735 0.018 250)',
                'content-subtle' => 'oklch(0.682 0.018 250)',

                'border' => 'oklch(0.36 0.018 250)',
                'border-strong' => 'oklch(0.6 0.018 250)',
                'focus' => 'oklch(0.724 0.1101 219)',             // ocean-300

                // Dark-theme buttons become lighter on hover and press.
                'primary' => 'oklch(0.642 0.1101 219)',           // ocean-400
                'primary-content' => 'oklch(0.217 0.018 250)',
                'primary-hover' => 'oklch(0.724 0.1101 219)',     // ocean-300
                'primary-active' => 'oklch(0.806 0.1101 219)',    // ocean-200
                'link' => 'oklch(0.724 0.1101 219)',              // ocean-300
                'link-hover' => 'oklch(0.765 0.1101 219)',

                'accent' => 'oklch(0.642 0.1101 219)',            // ocean-400
                'accent-content' => 'oklch(0.806 0.1101 219)',    // ocean-200
                'accent-surface' => 'oklch(0.314 0.0568 219)',    // ocean-800
                'neutral' => 'oklch(0.396 0.018 250)',            // neutral-700
                'neutral-content' => 'oklch(0.888 0.018 250)',    // neutral-100

                'highlight' => 'oklch(0.724 0.12 86)',            // warning-300
                'highlight-content' => 'oklch(0.17 0.018 250)',
                'table-header' => 'oklch(0.314 0.018 250)',       // neutral-800
                'table-header-content' => 'oklch(0.784 0.018 250)',

                'nav' => 'oklch(0.15 0.018 250)',                 // neutral-950
                'nav-content' => 'oklch(0.775 0.018 250)',
                'nav-content-muted' => 'oklch(0.66 0.018 250)',
                'nav-raised' => 'oklch(0.232 0.042 219)',         // ocean-900

                // L=0.77 keeps surface content within the contrast band on panels and pages.
                'danger' => 'oklch(0.642 0.12 27)',               // danger-400
                'danger-content' => 'oklch(0.15 0.018 250)',      // neutral-950
                'danger-surface' => 'oklch(0.314 0.12 27)',       // danger-800
                'danger-surface-content' => 'oklch(0.77 0.1104 27)',  // between danger-200/300
                'success' => 'oklch(0.642 0.12 150)',             // success-400
                'success-content' => 'oklch(0.15 0.018 250)',     // neutral-950
                'success-surface' => 'oklch(0.314 0.0865 150)',   // success-800
                'success-surface-content' => 'oklch(0.77 0.12 150)',  // between success-200/300
                'warning' => 'oklch(0.642 0.12 86)',              // warning-400
                'warning-content' => 'oklch(0.15 0.018 250)',     // neutral-950
                'warning-surface' => 'oklch(0.314 0.0643 86)',    // warning-800
                'warning-surface-content' => 'oklch(0.77 0.12 86)',  // between warning-200/300
                'info' => 'oklch(0.642 0.12 263)',                // info-400
                'info-content' => 'oklch(0.15 0.018 250)',        // neutral-950
                'info-surface' => 'oklch(0.314 0.12 263)',        // info-800
                'info-surface-content' => 'oklch(0.77 0.0971 263)',  // between info-200/300
            ],
        ],

        /*
         * No halation deliberately uses a 2.0–3.8 contrast band for readers whose
         * astigmatism makes WCAG contrast less readable. Do not raise these values.
         * It is not the default.
         *
         * Foregrounds use:
         * L_background + 0.53 * (L_low_glare_foreground - L_background)
         * Hue and chroma stay fixed, then values fit sRGB. Surfaces match Low-glare
         * dark. Solid fills also use the pull; status tints do not.
         */
        'no-halation' => [
            'name' => 'No halation',
            'contrast_ceiling' => 3.8,
            'contrast_floor' => 2.0,
            'tokens' => [
                // Surfaces match Low-glare dark.
                'surface' => 'oklch(0.207 0.018 250)',
                'surface-raised' => 'oklch(0.244 0.018 250)',
                'surface-sunken' => 'oklch(0.17 0.018 250)',
                'surface-overlay' => 'oklch(0.281 0.018 250)',

                'scrim' => 'oklch(0.05 0 0)',

                'content' => 'oklch(0.53 0.018 250)',
                'content-muted' => 'oklch(0.504 0.018 250)',
                'content-subtle' => 'oklch(0.476 0.018 250)',

                'border' => 'oklch(0.305 0.018 250)',
                'border-strong' => 'oklch(0.433 0.018 250)',
                'focus' => 'oklch(0.498 0.0901 219)',

                // Pull this solid fill toward the page; its states follow it.
                'primary' => 'oklch(0.438 0.0792 219)',
                'primary-content' => 'oklch(0.213 0.018 250)',
                'primary-hover' => 'oklch(0.481 0.087 219)',
                'primary-active' => 'oklch(0.525 0.0949 219)',
                'link' => 'oklch(0.498 0.0901 219)',
                'link-hover' => 'oklch(0.52 0.094 219)',

                'accent' => 'oklch(0.455 0.0823 219)',
                'accent-content' => 'oklch(0.575 0.104 219)',
                'accent-surface' => 'oklch(0.314 0.0568 219)',
                'neutral' => 'oklch(0.396 0.018 250)',
                'neutral-content' => 'oklch(0.657 0.018 250)',

                'highlight' => 'oklch(0.481 0.0985 86)',
                'highlight-content' => 'oklch(0.187 0.018 250)',
                'table-header' => 'oklch(0.314 0.018 250)',
                'table-header-content' => 'oklch(0.563 0.018 250)',

                'nav' => 'oklch(0.15 0.018 250)',
                // Measure navigation text against the lighter `nav-raised` band.
                'nav-content' => 'oklch(0.52 0.018 250)',
                'nav-content-muted' => 'oklch(0.459 0.018 250)',
                'nav-raised' => 'oklch(0.232 0.042 219)',

                // Solid statuses use the same pull. Tints stay distinct from surfaces.
                'danger' => 'oklch(0.438 0.12 27)',
                'danger-content' => 'oklch(0.177 0.018 250)',
                'danger-surface' => 'oklch(0.314 0.12 27)',
                'danger-surface-content' => 'oklch(0.523 0.1104 27)',
                'success' => 'oklch(0.438 0.12 150)',
                'success-content' => 'oklch(0.177 0.018 250)',
                'success-surface' => 'oklch(0.314 0.0865 150)',
                'success-surface-content' => 'oklch(0.523 0.12 150)',
                'warning' => 'oklch(0.438 0.0897 86)',
                'warning-content' => 'oklch(0.177 0.018 250)',
                'warning-surface' => 'oklch(0.314 0.0643 86)',
                'warning-surface-content' => 'oklch(0.523 0.1071 86)',
                'info' => 'oklch(0.438 0.12 263)',
                'info-content' => 'oklch(0.177 0.018 250)',
                'info-surface' => 'oklch(0.314 0.12 263)',
                'info-surface-content' => 'oklch(0.523 0.0971 263)',
            ],
        ],

    ],

];
