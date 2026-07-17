<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateImportSettingRequest;
use App\Models\ImportSetting;
use Illuminate\Http\RedirectResponse;

/**
 * Persists the global import settings (archive size cap + background mode).
 *
 * Thin, mirroring GeneralSettingsController::update(): resolve the singleton ->
 * (authorize in the Form Request) -> update -> redirect. Lives on the Export &
 * import page rather than General settings because this limit is import-specific
 * (same separation DatabaseConfigurationController keeps).
 */
class ImportSettingController extends Controller
{
    /**
     * Persist the size cap (MB → KB converted by the request) and background
     * toggle to the singleton row.
     */
    public function update(UpdateImportSettingRequest $request): RedirectResponse
    {
        ImportSetting::current()->update($request->settings());

        return redirect()
            ->route('admin.data.import.index')
            ->with('status', 'import-settings-updated');
    }
}
