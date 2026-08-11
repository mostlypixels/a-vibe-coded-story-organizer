<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAppearanceRequest;
use App\Support\FontChoice;
use App\Support\ThemePreset;
use Illuminate\Http\RedirectResponse;
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
    public function edit(): View
    {
        $user = auth()->user();

        return view('admin.appearance.edit', [
            'themes' => ThemePreset::all(),
            'active' => ThemePreset::resolve($user?->theme_slug)->slug,
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
            ),
        ]);
    }

    /**
     * Persist the picked preferences to the acting user only.
     */
    public function update(UpdateAppearanceRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()
            ->route('admin.appearance.edit')
            ->with('status', 'theme-updated');
    }
}
