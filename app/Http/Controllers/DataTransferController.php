<?php

namespace App\Http\Controllers;

use App\Enums\ImportPhase;
use App\Models\ImportSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Export & import section of the Admin Configuration area.
 *
 * Thin: returns the section view. The Export tab posts to ExportController; the
 * Import tab posts to ImportController. Provides:
 *  - the signed-in user's projects (ordered by name) so the Export form can
 *    offer them in its selector — the same access pattern the Dashboard uses;
 *  - the global ImportSetting singleton for the "Import settings" card;
 *  - the user's still-in-progress (non-completed) imports so the Import tab can
 *    list them with resume/discard actions (the view for these lands in task 08).
 */
class DataTransferController extends Controller
{
    public function index(Request $request): View
    {
        $projects = $request->user()->projects()->orderBy('name')->get();

        // Only imports that are NOT completed are actionable (resume/discard);
        // a completed import has nothing left to show on the Import tab.
        $imports = $request->user()->imports()
            ->where('phase', '!=', ImportPhase::Completed)
            ->latest()
            ->get();

        return view('admin.data.index', [
            'projects' => $projects,
            'importSetting' => ImportSetting::current(),
            'imports' => $imports,
        ]);
    }
}
