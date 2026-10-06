<?php

namespace App\Support;

use App\Services\ThemeStyleBlock;

/**
 * The slug => CSS value map the live preview applies.
 *
 * Shared by the Appearance page and the quick switcher, so only server-approved
 * values reach CSS properties. Theme values come from the renderer that paints
 * the saved page, never from a second walk of the config.
 */
final class AppearancePreviewMap
{
    /**
     * @return array<string, array<string, mixed>> one entry per stored appearance field
     */
    public static function build(ThemeStyleBlock $themeStyle): array
    {
        $stacks = array_map(
            fn (array $family): string => $family['stack'],
            config('fonts.families'),
        );

        return [
            'theme_slug' => array_map($themeStyle->declarations(...), ThemePreset::all()),
            'ui_font' => $stacks,
            'manuscript_font' => $stacks,
            'ui_scale' => config('fonts.ui_scales'),
            'manuscript_scale' => config('fonts.manuscript_scales'),
            // Already multiplied per surface, so the browser never does that maths.
            'manuscript_leading' => FontChoice::lineHeightsFor('manuscript'),
            'ui_leading' => FontChoice::lineHeightsFor('ui'),
        ];
    }
}
