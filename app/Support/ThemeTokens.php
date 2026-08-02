<?php

namespace App\Support;

/**
 * The canonical theme token vocabulary — reference data, following PlotlineColors.
 *
 * A token names a **role** ("the page background", "text on a primary button"), never a
 * hue. `bg-ocean-600` tells you a color is blue; it does not tell you where it may be
 * used, so it becomes a lie the moment a theme flips. `bg-primary` cannot.
 *
 * ## No shade suffixes
 *
 * There is no `bg-primary-600` — only `bg-primary`, `bg-primary-hover`,
 * `bg-primary-active`. The eleven-shade ramps are an *authoring* tool (see
 * `php artisan theme:ramp`); a preset stores flat, final values and Blade only ever
 * names roles.
 *
 * ## Every background has a chosen foreground
 *
 * PAIRS is the point of the whole vocabulary: a background token is never introduced
 * without deciding what text goes on it, so an unreadable combination is
 * unrepresentable. It is also the data the contrast matrix test iterates.
 *
 * Adding a token is a one-line change here, an entry in PAIRS, a value in every preset
 * in `config/themes.php`, and a line in `resources/css/app.css`'s role-token block.
 */
final class ThemeTokens
{
    /**
     * Every token, grouped by role. The order here is the order the emitted
     * `:root` rule uses, so the rendered block stays readable in devtools.
     *
     * @var list<string>
     */
    public const ALL = [
        // Surfaces — by elevation, not by color.
        'surface',
        'surface-raised',
        'surface-sunken',
        'surface-overlay',

        // The modal backdrop. Not a surface anything is painted on — it carries
        // no text — so it takes no foreground partner and no contrast floor. See
        // ThemeTokens::DECORATIVE.
        'scrim',

        // Content — by emphasis. Each must be legible on any surface above.
        'content',
        'content-muted',
        'content-subtle',

        // Lines and rings. `focus` is deliberately NOT an alias of `primary`: a theme
        // may need to move the focus ring independently, and it has its own contrast
        // rule (3:1 non-text, not 4.5:1).
        'border',
        'border-strong',
        'focus',

        // Interactive.
        'primary',
        'primary-content',
        'primary-hover',
        'primary-active',
        'link',
        'link-hover',
        'accent',
        'accent-content',
        'accent-surface',
        'neutral',
        'neutral-content',
        'highlight',
        'highlight-content',
        'table-header',
        'table-header-content',

        // The nav band. The navigation is a dark band above a light page, not an
        // elevated surface, so it gets its own family rather than `surface-raised`.
        'nav',
        'nav-content',
        'nav-content-muted',
        'nav-raised',

        // Status — four tokens each, not three. `<status>` is the solid fill,
        // `<status>-content` the text on it, `<status>-surface` the tinted background
        // and `<status>-surface-content` the text on *that*.
        //
        // The fourth exists because a trio leaves the tint's foreground unnamed, which
        // defaults it to `<status>` itself: measured against the tints that is 1.85
        // (warning), 3.14 (success), 4.47 (danger), 4.82 (info) — three of the four
        // below the 4.5 text floor. It also lets a preset hold a darker text shade than
        // the fill, which is what every tinted alert in the app already does.
        'danger',
        'danger-content',
        'danger-surface',
        'danger-surface-content',
        'success',
        'success-content',
        'success-surface',
        'success-surface-content',
        'warning',
        'warning-content',
        'warning-surface',
        'warning-surface-content',
        'info',
        'info-content',
        'info-surface',
        'info-surface-content',
    ];

    /**
     * Background token => the foreground tokens that may be painted on it.
     *
     * Keyed by background, because that is how a color is chosen: you pick the surface
     * first and then what goes on it. A background maps to a **list** rather than a
     * single foreground because most surfaces legitimately carry several — body text,
     * muted labels, links and hairlines all land on `surface`, and asserting only one
     * of them would leave `content-muted` unguarded.
     *
     * Every token in ALL appears here, as a key or inside a list. ThemePresetTest
     * enforces that, so a new token cannot be added without deciding its partner.
     *
     * @var array<string, list<string>>
     */
    public const PAIRS = [
        'surface' => [
            'content', 'content-muted', 'content-subtle', 'link', 'link-hover',
            'accent', 'border', 'border-strong', 'focus',
        ],
        'surface-raised' => [
            'content', 'content-muted', 'content-subtle', 'link', 'link-hover',
            'accent', 'border', 'border-strong', 'focus',
        ],
        'surface-sunken' => ['content', 'content-muted', 'content-subtle', 'link', 'link-hover'],
        'surface-overlay' => ['content', 'content-muted', 'content-subtle', 'link', 'link-hover'],

        'primary' => ['primary-content'],
        'primary-hover' => ['primary-content'],
        'primary-active' => ['primary-content'],

        'accent-surface' => ['accent-content'],
        'neutral' => ['neutral-content'],
        'highlight' => ['highlight-content'],
        'table-header' => ['table-header-content'],

        'nav' => ['nav-content', 'nav-content-muted', 'accent', 'focus'],
        'nav-raised' => ['nav-content', 'nav-content-muted', 'focus'],

        'danger' => ['danger-content'],
        'danger-surface' => ['danger-surface-content'],
        'success' => ['success-content'],
        'success-surface' => ['success-surface-content'],
        'warning' => ['warning-content'],
        'warning-surface' => ['warning-surface-content'],
        'info' => ['info-content'],
        'info-surface' => ['info-surface-content'],
    ];

    /**
     * Tokens judged as non-text UI, so a pair involving one of them is measured
     * against ColorContrast::NON_TEXT_FLOOR (3:1) instead of TEXT_FLOOR (4.5:1).
     *
     * These are shapes, not glyphs: a focus ring, a strong divider, the
     * active-navigation indicator. Holding them to the text floor would force them so
     * dark the UI reads as a wireframe.
     *
     * @var list<string>
     */
    public const NON_TEXT = ['accent', 'border-strong', 'focus'];

    /**
     * Tokens with **no** contrast floor at all — the contrast matrix skips them.
     *
     * `border` is the hairline between two table rows and around a card. WCAG 1.4.11
     * covers what identifies a component or its state, which a divider is not, and
     * holding it to even the 3:1 non-text floor moves it from `#e5e7eb` to `#8a8c90`:
     * every card edge in the app as a mid-grey line. `border-strong` and `focus` do
     * identify controls and state, so they stay in NON_TEXT and keep the floor.
     *
     * `scrim` carries no text either — it is the modal backdrop, an effect behind
     * the dialog rather than a surface anything is painted on.
     *
     * One named list rather than conditionals scattered through the tests — a token
     * exempt from the matrix has to be written down here to become exempt.
     *
     * @var list<string>
     */
    public const DECORATIVE = ['border', 'scrim'];
}
