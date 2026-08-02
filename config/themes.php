<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default preset
    |--------------------------------------------------------------------------
    |
    | The preset every unauthenticated surface paints with (the login page, the
    | public share page, `/`), and the fallback for any user who has never picked
    | one. Must be a key of `presets` below.
    |
    | Deliberately not a database row and not an env var: this is self-hosted, so
    | changing the site-wide look is a file edit, and a second place to set it is a
    | second place to disagree.
    |
    */

    'default' => 'daylight',

    /*
    |--------------------------------------------------------------------------
    | Contrast ceiling
    |--------------------------------------------------------------------------
    |
    | The upper bound on a preset's contrast ratios, used when *authoring* a preset
    | and by the tests guarding those choices — never while rendering a page.
    |
    | A ceiling exists because more contrast is not monotonically better: white on
    | black is 21:1 and is *worse* for astigmatism, where halation makes light text
    | bloom into the background. But it is a taste judgement that differs per preset
    | (a low-vision preset wants a higher ceiling than a low-glare one), so a preset
    | may override it with its own `contrast_ceiling`.
    |
    | The floors are the opposite kind of number — fixed WCAG minimums, the same for
    | every theme — so they live on the class that applies them:
    | App\Support\ColorContrast::TEXT_FLOOR / ::NON_TEXT_FLOOR. Do not restate them
    | here.
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
    | Each preset is `name` + `tokens` + an optional `contrast_ceiling`, read through
    | App\Support\ThemePreset. `tokens` must define exactly App\Support\ThemeTokens::ALL
    | — no missing key (which would render an empty custom property and silently lose a
    | color) and no unknown extra. ThemePresetTest enforces both.
    |
    | Values are **final**, never anchors: nothing computes a ramp while serving a
    | request. Generated presets come from `php artisan theme:ramp` and are pasted in,
    | with the anchors that produced them in a comment above the block.
    |
    | `name` is stored untranslated; the picker wraps it in `__()`, because config is
    | resolved (and cached) before a locale exists.
    |
    */

    'presets' => [

        /*
         * Daylight is the current look, ported value-for-value: every token below is
         * the literal value the app paints today, so renaming classes across ~900
         * usages cannot change how the default theme renders. That is what makes the
         * computed-style diff against `master` a meaningful gate.
         *
         * Hence the mixed notation — the trailing comment names the source. The five
         * hand-authored ramps (ocean / aqua / navy / sun / flame) are hex; Tailwind's
         * own palette is oklch in v4, so those tokens carry oklch verbatim rather than
         * a hex approximation that would shift the pixel by a digit.
         *
         * Regularizing Daylight onto even OKLCH ramps is deliberate follow-up work
         * with its own browser pass, not part of the rename.
         */
        'daylight' => [
            'name' => 'Daylight',
            'tokens' => [
                'surface' => 'oklch(96.7% 0.003 264.542)',        // gray-100
                'surface-raised' => '#ffffff',                    // white
                'surface-sunken' => 'oklch(98.5% 0.002 247.839)', // gray-50
                'surface-overlay' => '#ffffff',                   // white

                // The modal backdrop — what `x-modal` has always painted (`bg-gray-500
                // opacity-75`), so this is still rename-only for Daylight.
                'scrim' => 'oklch(55.1% 0.027 264.364)',          // gray-500

                'content' => '#023047',                           // navy-900
                'content-muted' => 'oklch(55.1% 0.027 264.364)',  // gray-500
                'content-subtle' => 'oklch(70.7% 0.022 261.325)', // gray-400

                'border' => 'oklch(92.8% 0.006 264.531)',         // gray-200
                'border-strong' => 'oklch(87.2% 0.01 258.338)',   // gray-300
                'focus' => '#219ebc',                             // ocean-500

                'primary' => '#023047',                           // navy-900
                'primary-content' => '#ffffff',                   // white
                'primary-hover' => '#033a4d',                     // navy-800
                'primary-active' => '#011f2f',                    // navy-950
                'link' => '#1b809a',                              // ocean-600
                'link-hover' => '#185767',                        // ocean-800

                // The loud fuchsia placeholder the Tailwind 4 port left on the
                // active-navigation indicator, so it still announces itself in the
                // browser pass. Task 12 re-authors it.
                'accent' => 'oklch(66.7% 0.295 322.15)',          // fuchsia-500
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
                'nav-raised' => '#184a58',                        // ocean-900

                'danger' => 'oklch(57.7% 0.245 27.325)',          // red-600
                'danger-content' => '#ffffff',                    // white
                'danger-surface' => 'oklch(97.1% 0.013 17.38)',   // red-50
                'danger-surface-content' => 'oklch(44.4% 0.177 26.899)', // red-800
                'success' => 'oklch(62.7% 0.194 149.214)',        // green-600
                'success-content' => '#ffffff',                   // white
                'success-surface' => 'oklch(98.2% 0.018 155.826)', // green-50
                'success-surface-content' => 'oklch(44.8% 0.119 151.328)', // green-800
                'warning' => 'oklch(79.5% 0.184 86.047)',         // yellow-500
                'warning-content' => '#ffffff',                   // white
                'warning-surface' => 'oklch(98.7% 0.026 102.212)', // yellow-50
                'warning-surface-content' => 'oklch(47.6% 0.114 61.907)', // yellow-800
                'info' => 'oklch(54.6% 0.245 262.881)',           // blue-600
                'info-content' => '#ffffff',                      // white
                'info-surface' => 'oklch(97% 0.014 254.604)',     // blue-50
                'info-surface-content' => 'oklch(42.4% 0.199 265.638)', // blue-800
            ],
        ],

        /*
         * A genuine dark theme: the page is dark and elevation goes *up* in lightness,
         * rather than a light theme with inverted text.
         *
         * > [!WARNING]
         * > Provisional, and known to be wrong in places. It exists so that the sweep
         * > tasks have a second preset to switch to — an element still painted with a
         * > hue-named class glows light against this and is impossible to miss. It is a
         * > detector. **Task 12 re-authors it** against the settled vocabulary, with a
         * > full contrast matrix and a browser pass; do not polish it here.
         *
         * Generated by `php artisan theme:ramp`, six ramps, each shade picked by hand:
         *
         *   neutral  oklch(0.6 0.018 250)                 surfaces, content, lines
         *   ocean    #219ebc          --max-chroma=0.12   primary, links, focus, accent
         *   danger   oklch(0.6 0.2 27)  --max-chroma=0.12
         *   success  oklch(0.6 0.2 150) --max-chroma=0.12
         *   warning  oklch(0.6 0.2 86)  --max-chroma=0.12
         *   info     oklch(0.6 0.2 263) --max-chroma=0.12
         *
         * Every accent is capped at 0.12 chroma. Saturated colour at high lightness is
         * what makes a dark theme tiring, and the cap is the one thing this preset is
         * already confident about.
         */
        'low-glare-dark' => [
            'name' => 'Low-glare dark',
            'tokens' => [
                // Elevation rises with lightness: the page is the floor, a card sits
                // above it, an overlay above that, and a sunken well below.
                'surface' => 'oklch(0.232 0.018 250)',            // neutral-900
                'surface-raised' => 'oklch(0.314 0.018 250)',     // neutral-800
                'surface-sunken' => 'oklch(0.15 0.018 250)',      // neutral-950
                'surface-overlay' => 'oklch(0.396 0.018 250)',    // neutral-700

                // Near-black, not a mid-grey: a scrim has to dim whatever it sits over,
                // and `content-muted` (this preset's body-text grey) would instead wash
                // the already-dark page out pale, which is the bug this token exists to
                // fix — see resolution-log.md's "modal scrim" entry.
                'scrim' => 'oklch(0.05 0 0)',

                // Body text stops at 12:1 rather than running to white: white on near
                // black is 19:1 and blooms.
                'content' => 'oklch(0.888 0.018 250)',            // neutral-100
                'content-muted' => 'oklch(0.724 0.018 250)',      // neutral-300
                'content-subtle' => 'oklch(0.642 0.018 250)',     // neutral-400

                'border' => 'oklch(0.396 0.018 250)',             // neutral-700
                'border-strong' => 'oklch(0.56 0.018 250)',       // neutral-500
                'focus' => 'oklch(0.724 0.1101 219)',             // ocean-300

                // A dark theme's buttons get *lighter* on hover, not darker.
                'primary' => 'oklch(0.642 0.1101 219)',           // ocean-400
                'primary-content' => 'oklch(0.15 0.018 250)',     // neutral-950
                'primary-hover' => 'oklch(0.724 0.1101 219)',     // ocean-300
                'primary-active' => 'oklch(0.806 0.1101 219)',    // ocean-200
                'link' => 'oklch(0.724 0.1101 219)',              // ocean-300
                'link-hover' => 'oklch(0.806 0.1101 219)',        // ocean-200

                'accent' => 'oklch(0.642 0.1101 219)',            // ocean-400
                'accent-content' => 'oklch(0.806 0.1101 219)',    // ocean-200
                'accent-surface' => 'oklch(0.314 0.0568 219)',    // ocean-800
                'neutral' => 'oklch(0.396 0.018 250)',            // neutral-700
                'neutral-content' => 'oklch(0.97 0.0147 250)',    // neutral-50

                'highlight' => 'oklch(0.724 0.12 86)',            // warning-300
                'highlight-content' => 'oklch(0.15 0.018 250)',   // neutral-950
                'table-header' => 'oklch(0.314 0.018 250)',       // neutral-800
                'table-header-content' => 'oklch(0.888 0.018 250)', // neutral-100

                'nav' => 'oklch(0.15 0.018 250)',                 // neutral-950
                'nav-content' => 'oklch(0.888 0.018 250)',        // neutral-100
                'nav-content-muted' => 'oklch(0.642 0.018 250)',  // neutral-400
                'nav-raised' => 'oklch(0.232 0.042 219)',         // ocean-900

                // Each status is a mid-lightness fill carrying dark text, plus a dark
                // tinted panel carrying light text.
                'danger' => 'oklch(0.642 0.12 27)',               // danger-400
                'danger-content' => 'oklch(0.15 0.018 250)',      // neutral-950
                'danger-surface' => 'oklch(0.314 0.12 27)',       // danger-800
                'danger-surface-content' => 'oklch(0.806 0.1104 27)', // danger-200
                'success' => 'oklch(0.642 0.12 150)',             // success-400
                'success-content' => 'oklch(0.15 0.018 250)',     // neutral-950
                'success-surface' => 'oklch(0.314 0.0865 150)',   // success-800
                'success-surface-content' => 'oklch(0.888 0.12 150)', // success-100
                'warning' => 'oklch(0.642 0.12 86)',              // warning-400
                'warning-content' => 'oklch(0.15 0.018 250)',     // neutral-950
                'warning-surface' => 'oklch(0.314 0.0643 86)',    // warning-800
                'warning-surface-content' => 'oklch(0.888 0.12 86)', // warning-100
                'info' => 'oklch(0.642 0.12 263)',                // info-400
                'info-content' => 'oklch(0.15 0.018 250)',        // neutral-950
                'info-surface' => 'oklch(0.314 0.12 263)',        // info-800
                'info-surface-content' => 'oklch(0.888 0.0543 263)', // info-100
            ],
        ],

    ],

];
