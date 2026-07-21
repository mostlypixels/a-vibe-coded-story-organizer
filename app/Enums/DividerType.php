<?php

namespace App\Enums;

/**
 * The style of dividers between scenes within a chapter.
 * Drives the rendering in the EPUB's chapter pages.
 * Image dividers are V2 — not included here yet.
 */
enum DividerType: string
{
    case HorizontalRule = 'horizontal_rule';
    case Decorative = 'decorative';

    public function label(): string
    {
        return match ($this) {
            self::HorizontalRule => 'Horizontal rule',
            self::Decorative => 'Decorative (centered ornament)',
        };
    }

    /**
     * The HTML snippet to render as a divider between scenes.
     * HorizontalRule renders <hr/>, Decorative renders a centered ornament
     * styled by styles.css.
     */
    public function dividerHtml(): string
    {
        return match ($this) {
            self::HorizontalRule => '<hr/>',
            self::Decorative => '<p class="divider">* * *</p>',
        };
    }
}
