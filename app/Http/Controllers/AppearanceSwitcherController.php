<?php

namespace App\Http\Controllers;

use App\Services\ThemeStyleBlock;
use App\Support\AppearancePreviewMap;
use App\Support\FontChoice;
use App\Support\ScaleLabel;
use App\Support\ThemePreset;
use Illuminate\View\View;

/**
 * Serves the quick appearance switcher panel as a layout-free fragment.
 *
 * Reads only the acting user's own columns. The writes go through
 * AppearanceController@update, so no policy and no project walk apply here.
 */
class AppearanceSwitcherController extends Controller
{
    public function __invoke(ThemeStyleBlock $themeStyle): View
    {
        $user = auth()->user();
        $fonts = FontChoice::resolve(
            $user?->ui_font,
            $user?->manuscript_font,
            $user?->ui_scale,
            $user?->manuscript_scale,
            $user?->manuscript_leading,
            $user?->ui_leading,
        );

        // Field => [slug => value list, label format].
        $scales = [
            'ui_scale' => [config('fonts.ui_scales'), 'px'],
            'manuscript_scale' => [config('fonts.manuscript_scales'), 'times'],
            'ui_leading' => [config('fonts.leading'), 'times'],
            'manuscript_leading' => [config('fonts.leading'), 'times'],
        ];

        return view('appearance.switcher', [
            'themes' => ThemePreset::all(),
            'families' => config('fonts.families'),
            'fonts' => $fonts,
            'config' => [
                'url' => route('admin.appearance.update'),
                'previewMap' => AppearancePreviewMap::build($themeStyle),
                'steps' => array_map(fn (array $scale): array => array_keys($scale[0]), $scales),
                'labels' => array_map(
                    fn (array $scale): array => array_map(
                        fn (string $value): string => ScaleLabel::format($value, $scale[1]),
                        $scale[0],
                    ),
                    $scales,
                ),
                'active' => [
                    'theme_slug' => ThemePreset::resolve($user?->theme_slug)->slug,
                    'ui_font' => $fonts->uiSlug,
                    'manuscript_font' => $fonts->manuscriptSlug,
                    'ui_scale' => $fonts->uiScaleSlug,
                    'manuscript_scale' => $fonts->manuscriptScaleSlug,
                    'ui_leading' => $fonts->uiLeadingSlug,
                    'manuscript_leading' => $fonts->leadingSlug,
                ],
                'messages' => ['failed' => __('Could not save. Try again.')],
            ],
        ]);
    }
}
