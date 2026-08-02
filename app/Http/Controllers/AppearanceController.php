<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateThemeSettingRequest;
use App\Support\ThemePreset;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Appearance & accessibility section of the Admin Configuration area: the
 * per-user theme preset picker (theme-switcher spec, task 04).
 *
 * Thin: resolve the preset list + the active one -> (authorize in the Form
 * Request) -> write to $request->user() -> redirect. Mirrors
 * GeneralSettingsController. No policy class and no ProjectPolicy walk — this
 * preference is owned by no Project, and the update always writes to the
 * acting user, so there is no cross-user case to guard.
 */
class AppearanceController extends Controller
{
    public function edit(): View
    {
        return view('admin.appearance.edit', [
            'themes' => ThemePreset::all(),
            'active' => ThemePreset::resolve(auth()->user()?->theme_slug)->slug,
        ]);
    }

    /**
     * Persist the picked preset to the acting user only.
     */
    public function update(UpdateThemeSettingRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()
            ->route('admin.appearance.edit')
            ->with('status', 'theme-updated');
    }
}
