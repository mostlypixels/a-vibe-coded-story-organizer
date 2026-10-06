<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAppearanceRequest;
use App\Services\ThemeStyleBlock;
use App\Support\AppearancePreviewMap;
use App\Support\DateFormat;
use App\Support\FontChoice;
use App\Support\LocaleChoice;
use App\Support\ThemePreset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Appearance & accessibility section of the Admin Configuration area: the
 * per-user theme preset and font picker.
 *
 * Thin: resolve the preset/font lists + the active ones -> (authorize in the
 * Form Request) -> write to $request->user() -> redirect. Mirrors
 * GeneralSettingsController. No policy class and no ProjectPolicy walk — this
 * preference is owned by no Project, and the update always writes to the
 * acting user, so there is no cross-user case to guard.
 */
class AppearanceController extends Controller
{
    public function edit(ThemeStyleBlock $themeStyle): View
    {
        $user = auth()->user();
        $themes = ThemePreset::all();

        return view('admin.appearance.edit', [
            'themes' => $themes,
            'active' => ThemePreset::resolve($user?->theme_slug)->slug,
            'previewMap' => AppearancePreviewMap::build($themeStyle),
            'families' => config('fonts.families'),
            'uiScales' => config('fonts.ui_scales'),
            'manuscriptScales' => config('fonts.manuscript_scales'),
            'leadings' => config('fonts.leading'),
            'fonts' => FontChoice::resolve(
                $user?->ui_font,
                $user?->manuscript_font,
                $user?->ui_scale,
                $user?->manuscript_scale,
                $user?->manuscript_leading,
                $user?->ui_leading,
            ),
            'locales' => LocaleChoice::all(),
            'activeLocale' => LocaleChoice::resolve($user?->locale)->slug,
            // Keyed by slug so the picker can show a live sample without a round trip.
            'localeSamples' => array_map(
                fn (LocaleChoice $locale): string => DateFormat::dateTime(Carbon::now(), $locale),
                LocaleChoice::all(),
            ),
        ]);
    }

    /**
     * Persist the picked preferences to the acting user only. The quick switcher
     * saves over AJAX and gets 204; the Appearance form gets the redirect.
     */
    public function update(UpdateAppearanceRequest $request): RedirectResponse|Response
    {
        $request->user()->update($request->validated());

        if ($request->wantsJson()) {
            return response()->noContent();
        }

        return redirect()
            ->route('admin.appearance.edit')
            ->with('status', 'theme-updated');
    }
}
