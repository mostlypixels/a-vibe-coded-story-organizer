<?php

namespace App\Support;

/**
 * Defines theme colors by interface role instead of hue or shade.
 *
 * Each background must declare its allowed foregrounds in {@see PAIRS}. The
 * contrast tests use these pairs.
 */
final class ThemeTokens
{
    /** @var list<string> Tokens in CSS output order. */
    public const ALL = [
        // Surfaces by elevation.
        'surface',
        'surface-raised',
        'surface-sunken',
        'surface-overlay',

        // The modal backdrop carries no content.
        'scrim',

        // Content by emphasis.
        'content',
        'content-muted',
        'content-subtle',

        // Focus is independent because it uses the non-text contrast threshold.
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

        // The navigation band has its own foregrounds.
        'nav',
        'nav-content',
        'nav-content-muted',
        'nav-raised',

        // Each status has separate foregrounds for its solid and tinted backgrounds.
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
     * Maps each background to all foregrounds that it can contain.
     *
     * @var array<string, list<string>>
     */
    public const PAIRS = [
        // Tinted status foregrounds can also appear directly on the page surface.
        'surface' => [
            'content', 'content-muted', 'content-subtle', 'link', 'link-hover',
            'accent', 'border', 'border-strong', 'focus',
            'danger-surface-content', 'success-surface-content',
            'warning-surface-content', 'info-surface-content',
        ],
        'surface-raised' => [
            'content', 'content-muted', 'content-subtle', 'link', 'link-hover',
            'accent', 'border', 'border-strong', 'focus',
            'danger-surface-content', 'success-surface-content',
            'warning-surface-content', 'info-surface-content',
        ],
        'surface-sunken' => [
            'content', 'content-muted', 'content-subtle', 'link', 'link-hover',
            'danger-surface-content', 'success-surface-content',
            'warning-surface-content', 'info-surface-content',
        ],
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

    /** @var list<string> Shapes that use the non-text contrast threshold. */
    public const NON_TEXT = ['accent', 'border-strong', 'focus'];

    /**
     * Decorative tokens that do not identify content, controls, or state.
     *
     * @var list<string>
     */
    public const DECORATIVE = ['border', 'scrim'];
}
