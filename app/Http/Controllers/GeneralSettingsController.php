<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCrawlerSettingRequest;
use App\Models\CrawlerSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * General settings section of the Admin Configuration area.
 *
 * Thin: resolve the CrawlerSetting singleton -> (authorize in the Form Request)
 * -> update -> redirect. This is the relocated search-engine (crawler) form,
 * formerly CrawlerSettingController; only the redirect route name changed.
 */
class GeneralSettingsController extends Controller
{
    /**
     * Show the search-engine visibility form for the singleton.
     */
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'setting' => CrawlerSetting::current(),
        ]);
    }

    /**
     * Persist the toggle + whitelist to the singleton row.
     */
    public function update(UpdateCrawlerSettingRequest $request): RedirectResponse
    {
        CrawlerSetting::current()->update($request->validated());

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', 'crawler-settings-updated');
    }
}
