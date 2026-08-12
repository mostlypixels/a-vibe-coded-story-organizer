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

    'default' => 'low-glare-dark',

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
    | One preset overrides them anyway, with its own `contrast_floor`. That is the
    | escape hatch for a reader the minimums make worse off, not a default anything
    | falls back to — see the `no-halation` preset below.
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
         * Daylight is the app's original look, ported value-for-value through the
         * class rename — the mixed notation is why the trailing comments name each
         * source. The five hand-authored ramps (ocean / aqua / navy / sun / flame) are
         * hex; Tailwind's own palette is oklch in v4, so those tokens carry oklch
         * verbatim rather than a hex approximation that would shift the pixel.
         *
         * Ten values are **not** the original: every pair that failed its WCAG floor,
         * re-authored once the rename was complete and the whole matrix could be
         * measured. Accessibility outranks pixel-stability.
         * Each carries a `was` comment. Nothing else moved.
         *
         * The 18.0 ceiling is Daylight's own: white on navy-950 (the pressed primary
         * button) measures 16.96, which is emphatic rather than unreadable. Lowering
         * the navy to satisfy the house 15.0 default would repaint the app's signature
         * colour to fix a number that is not an accessibility problem.
         *
         * Regularizing Daylight onto even OKLCH ramps is deliberate follow-up work
         * with its own browser pass, not part of this.
         */
        'daylight' => [
            'name' => 'Daylight',
            'contrast_ceiling' => 18.0,
            'tokens' => [
                'surface' => 'oklch(96.7% 0.003 264.542)',        // gray-100
                'surface-raised' => '#ffffff',                    // white
                'surface-sunken' => 'oklch(98.5% 0.002 247.839)', // gray-50
                'surface-overlay' => '#ffffff',                   // white

                // The modal backdrop — what `x-modal` has always painted (`bg-gray-500
                // opacity-75`), so this is still rename-only for Daylight.
                'scrim' => 'oklch(55.1% 0.027 264.364)',          // gray-500

                'content' => '#023047',                           // navy-900

                // The three body-text weights all clear 4.5:1 on `surface`, which is
                // the darkest of the four. That squeezes them together — the window
                // between the floor and the 18.0 ceiling is only so wide — so the
                // emphasis steps are 4.8 / 6.9 / 12.6, not the old 2.4 / 4.4 / 12.6.
                'content-muted' => 'oklch(44.6% 0.03 256.802)',   // gray-600; was gray-500 (4.39)
                'content-subtle' => 'oklch(53% 0.027 264.364)',   // gray-500 darkened; was gray-400 (2.36)

                'border' => 'oklch(92.8% 0.006 264.531)',         // gray-200

                // Every input's visible boundary, so 1.4.11 applies to it and not to
                // the decorative `border` beside it.
                'border-strong' => 'oklch(62% 0.024 261.325)',    // was gray-300 (1.34)

                // `focus` has to clear 3:1 against the light page *and* against the
                // dark nav band, and the two pull in opposite directions: the window
                // only exists if `surface` is at least 9× `nav-raised` in luminance.
                // ocean-500 over ocean-900 missed it by a hair, so both moved a step —
                // this is why `nav-raised` below is not the ramp value it was.
                'focus' => '#1e93af',                             // was ocean-500 (2.86)

                'primary' => '#023047',                           // navy-900
                'primary-content' => '#ffffff',                   // white
                'primary-hover' => '#033a4d',                     // navy-800
                'primary-active' => '#011f2f',                    // navy-950
                'link' => '#18697e',                              // ocean-700; was ocean-600 (4.15)
                'link-hover' => '#185767',                        // ocean-800

                // Was a deliberately loud fuchsia placeholder, left by the Tailwind 4
                // port so the active-navigation indicator announced itself during the
                // sweep. ocean-600 is the darkest ocean that still clears 3:1 on the
                // nav band, and it keeps "this is the one you are on" in the same
                // colour family as `accent-surface` / `accent-content`.
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

                // White on yellow is 1.92:1 — the worst pair in the app, and no
                // yellow fixes it: the fill would have to go brown before white
                // worked. Dark text on yellow is the hazard convention anyway, so the
                // *foreground* moved instead and warning stays yellow.
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
         * Dim, not dark: a light theme with the white taken out of it, for anyone who
         * finds a white page glaring but does not want to read pale text off black.
         * The page sits at neutral-100 and elevation rises from there, so nothing in
         * the UI is brighter than about 95% lightness.
         *
         * Generated by `php artisan theme:ramp`, six ramps:
         *
         *   neutral  oklch(0.6 0.012 255)                 surfaces, content, lines
         *   ocean    #219ebc          --max-chroma=0.14   primary, links, focus, accent
         *   danger   oklch(0.6 0.2 27)  --max-chroma=0.14
         *   success  oklch(0.6 0.2 150) --max-chroma=0.14
         *   warning  oklch(0.6 0.2 86)  --max-chroma=0.14  (also highlight / table header)
         *   info     oklch(0.6 0.2 263) --max-chroma=0.14
         *
         * The four surfaces are the one place this preset leaves the ramp: elevation in
         * a light theme is a few percent of lightness, not the ramp's 0.082 stride, and
         * four whole shades apart would spread the surfaces so far that no single body
         * text colour could clear 4.5:1 on the darkest and stay under 12:1 on the
         * lightest. So they are half-steps of the neutral ramp; everything else is a
         * shade off one of the six ramps above.
         *
         * The 12.0 ceiling is what "moderate contrast" means here — comfortably above
         * the 4.5 floor, well short of Daylight's near-black-on-white.
         */
        'dusk' => [
            'name' => 'Dusk',
            'contrast_ceiling' => 12.0,
            'tokens' => [
                // Elevation rises with lightness, and the page is not the top of it:
                // a card lifts off the page and an overlay lifts off the card.
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

                // A light theme's buttons darken on press.
                'primary' => 'oklch(0.478 0.0864 219)',           // ocean-600
                'primary-content' => 'oklch(0.97 0.012 255)',     // neutral-50
                'primary-hover' => 'oklch(0.396 0.0716 219)',     // ocean-700
                'primary-active' => 'oklch(0.314 0.0568 219)',    // ocean-800
                // A step darker than `primary`: link text also has to clear 4.5:1 on
                // `surface-sunken`, which a button never sits on.
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

                // The nav band has to stay dark even in a light theme, and it is the
                // reason `focus` sits where it does: one focus colour has to clear 3:1
                // against both the dim page and this band, which only leaves a window
                // if the band is at least 9× darker than the page.
                'nav' => 'oklch(0.15 0.0271 219)',                // ocean-950
                'nav-content' => 'oklch(0.806 0.012 255)',        // neutral-200
                'nav-content-muted' => 'oklch(0.642 0.1101 219)', // ocean-400
                'nav-raised' => 'oklch(0.232 0.042 219)',         // ocean-900

                // Yellow takes dark text and the other three take light — the same
                // split Daylight ended up with, for the same reason: no yellow light
                // enough to still read as a warning carries white at 4.5:1.
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
         * A genuine dark theme: the page is dark and elevation goes *up* in lightness,
         * rather than a light theme with inverted text.
         *
         * Deliberately not white-on-black. White on near-black measures 19:1 and is
         * *worse* for astigmatism, where halation makes light text bloom into the
         * background — hence the 10.0 ceiling, the lowest of the three, and the chroma
         * cap on every accent. Saturated colour at high lightness is the other half of
         * what makes a dark theme tiring.
         *
         * The ceiling is what shapes this palette. Body text has to clear 4.5:1 on the
         * lightest surface and stay under 10:1 on the darkest, which leaves a window
         * barely twice as wide as it is tall — so the four surfaces are packed into
         * half a ramp step of each other and the three content weights into rather
         * less. Widening either end breaks the other.
         *
         * Generated by `php artisan theme:ramp`, six ramps:
         *
         *   neutral  oklch(0.6 0.018 250)                 surfaces, content, lines
         *   ocean    #219ebc          --max-chroma=0.12   primary, links, focus, accent
         *   danger   oklch(0.6 0.2 27)  --max-chroma=0.12
         *   success  oklch(0.6 0.2 150) --max-chroma=0.12
         *   warning  oklch(0.6 0.2 86)  --max-chroma=0.12
         *   info     oklch(0.6 0.2 263) --max-chroma=0.12
         *
         * Surfaces and content weights are half-steps between those ramps' shades, for
         * the reason above; the accents are ramp shades.
         */
        'low-glare-dark' => [
            'name' => 'Low-glare dark',
            'contrast_ceiling' => 10.0,
            'tokens' => [
                // Elevation rises with lightness: the page is the floor, a card sits
                // above it, an overlay above that, and a sunken well below. Half a ramp
                // step apart, not a whole one — a full stride between the darkest and
                // lightest surface leaves no lightness where body text clears the floor
                // on one and stays under the ceiling on the other.
                'surface' => 'oklch(0.207 0.018 250)',
                'surface-raised' => 'oklch(0.244 0.018 250)',
                'surface-sunken' => 'oklch(0.17 0.018 250)',
                'surface-overlay' => 'oklch(0.281 0.018 250)',

                // Near-black, not a mid-grey: a scrim has to dim whatever it sits over,
                // and `content-muted` (this preset's body-text grey) would instead wash
                // the already-dark page out pale, which is the bug this token exists
                // to fix.
                'scrim' => 'oklch(0.05 0 0)',

                // Body text stops well short of white — white on this page measures
                // around 17:1 and blooms. The three weights sit inside a single ramp
                // step of each other, because the 10.0 ceiling and the 4.5 floor leave
                // only that much room once the four surfaces have taken their share.
                'content' => 'oklch(0.784 0.018 250)',
                'content-muted' => 'oklch(0.735 0.018 250)',
                'content-subtle' => 'oklch(0.682 0.018 250)',

                'border' => 'oklch(0.36 0.018 250)',
                'border-strong' => 'oklch(0.6 0.018 250)',
                'focus' => 'oklch(0.724 0.1101 219)',             // ocean-300

                // A dark theme's buttons get *lighter* on hover, not darker. The text
                // on them is not the darkest surface value: against the pressed state
                // that would read 11:1, over this preset's ceiling.
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
                // A shade below `content`: the nav band is darker than the page, so the
                // same lightness would read 10.2:1 there — over the ceiling.
                'nav-content' => 'oklch(0.775 0.018 250)',
                'nav-content-muted' => 'oklch(0.66 0.018 250)',
                'nav-raised' => 'oklch(0.232 0.042 219)',         // ocean-900

                // Each status is a mid-lightness fill carrying dark text, plus a dark
                // tinted panel carrying light text.
                //
                // The four `-surface-content` values sit at L=0.77, off the ramp's own
                // 200 step (0.806). They are not only the tint's foreground: the app also
                // paints them straight onto the page, so `ThemeTokens::PAIRS` declares
                // them against all three surfaces — and at 0.806 they read up to 10.92:1
                // on `surface-sunken`, over this preset's 10.0 ceiling. 0.77 lands every
                // combination inside the band (max 9.66) while holding 6.25:1 on the tint,
                // well clear of the 4.5 floor.
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
         * Low-glare dark taken further, for severe halation: the same four surfaces,
         * value for value, with every foreground moved 53% of the way toward the
         * background it is painted on.
         *
         * ## Below the WCAG floors, on purpose
         *
         * The band is 2.03–3.71, so this preset declares a `contrast_floor` and every
         * pair in it fails WCAG 2.x. That is the point rather than a defect: for a
         * reader whose astigmatism blooms light glyphs into a dark page, the 4.5:1
         * minimum makes the text *less* readable, and no amount of hue or weight fixes
         * a halo. It is one preset among four, never the default, and a reader who
         * needs the minimums has three others.
         *
         * Do not "repair" these numbers upward. Raising them is deleting the preset.
         *
         * ## How the values were derived
         *
         * Not from `theme:ramp` — the ramps stop well above this band. Each foreground
         * is `L_background + 0.53 * (L_low_glare_foreground - L_background)`, hue and
         * chroma untouched, then fitted to sRGB. 0.53 is measured, not chosen: it is
         * the factor that puts `content` and `content-muted` on `surface-raised` at the
         * 3.09:1 and 2.76:1 of the reference rendering this preset copies.
         *
         * Backgrounds keep their low-glare values, with one class of exception: the
         * six *solid fills* — `primary`, `highlight` and the four statuses — take the
         * same pull as a foreground, because a saturated block of colour stays the
         * loudest thing on a page otherwise. The status tints do not move; see the
         * notes on each below.
         *
         * `primary-hover` and `primary-active` follow `primary` by the same factor: the
         * three states share one `primary-content`, and at full stride the pressed
         * state carried it at 4.82:1, outside the band the rest of the preset holds to.
         */
        'no-halation' => [
            'name' => 'No halation',
            'contrast_ceiling' => 3.8,
            'contrast_floor' => 2.0,
            'tokens' => [
                // Identical to Low-glare dark. The elevation this theme has left is
                // the surfaces themselves, so they are the one thing not compressed.
                'surface' => 'oklch(0.207 0.018 250)',
                'surface-raised' => 'oklch(0.244 0.018 250)',
                'surface-sunken' => 'oklch(0.17 0.018 250)',
                'surface-overlay' => 'oklch(0.281 0.018 250)',

                'scrim' => 'oklch(0.05 0 0)',

                // The three weights are ~0.027 of lightness apart, half of Low-glare
                // dark's spacing. Any wider and the top weight leaves the ceiling.
                'content' => 'oklch(0.53 0.018 250)',
                'content-muted' => 'oklch(0.504 0.018 250)',
                'content-subtle' => 'oklch(0.476 0.018 250)',

                'border' => 'oklch(0.305 0.018 250)',
                'border-strong' => 'oklch(0.433 0.018 250)',
                'focus' => 'oklch(0.498 0.0901 219)',

                // A primary button is the largest saturated block on a page, so at
                // Low-glare dark's lightness it stayed the brightest thing in a theme
                // built to have no brightest thing. Pulled toward the page like a
                // foreground; the two states follow it, and the chroma drop is the
                // gamut fit, not a second choice — hue 219 leaves sRGB below L 0.5.
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

                // Pulled like the other fills, though a search match is the one thing
                // here meant to catch the eye. It still does: nothing else on a page of
                // prose is yellow, and hue carries it once lightness cannot.
                'highlight' => 'oklch(0.481 0.0985 86)',
                'highlight-content' => 'oklch(0.187 0.018 250)',
                'table-header' => 'oklch(0.314 0.018 250)',
                'table-header-content' => 'oklch(0.563 0.018 250)',

                'nav' => 'oklch(0.15 0.018 250)',
                // Measured against `nav-raised`, not `nav`: the raised band is the
                // lighter of the two the nav text crosses, so anchoring there is what
                // keeps the muted weight off the floor.
                'nav-content' => 'oklch(0.52 0.018 250)',
                'nav-content-muted' => 'oklch(0.459 0.018 250)',
                'nav-raised' => 'oklch(0.232 0.042 219)',

                // The four solid fills take the same pull as `primary`, and for the same
                // reason — a Delete button is a saturated block, and one loud status
                // would undo the preset on the page that shows it. They move together:
                // a status is told apart here by hue, so a lightness that differs
                // between the four would read as importance nobody meant.
                //
                // The *tints* do not move. At L 0.314 they are already close to the
                // surfaces, and pulling them collapses the panel into the card behind
                // it. Only the text on a tint moves.
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
