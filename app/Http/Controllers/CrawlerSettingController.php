<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCrawlerSettingRequest;
use App\Models\CrawlerSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The authenticated in-app screen for the global crawler policy. Thin: resolve
 * the singleton -> (authorize in the Form Request) -> update -> redirect.
 */
class CrawlerSettingController extends Controller
{
    /**
     * Show the settings form for the singleton.
     */
    public function edit(): View
    {
        return view('settings.crawlers.edit', [
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
            ->route('crawler-settings.edit')
            ->with('status', 'crawler-settings-updated');
    }
}
