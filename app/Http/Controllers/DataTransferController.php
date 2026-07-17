<?php

namespace App\Http\Controllers;

use App\Enums\ImportPhase;
use App\Models\ImportSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Export & import section of the Admin Configuration area.
 *
 * Split (task 03) into three thin GET actions — one per server-rendered page,
 * reached via ordinary links (resources/views/admin/data/partials/subnav.blade.php)
 * rather than JavaScript tabs. Each POST that mutates something still lives on its
 * own controller (ExportController, EpubExportController, ImportController,
 * ImportSettingController); this controller only assembles the read-only data
 * each page needs to render its form.
 */
class DataTransferController extends Controller
{
    /**
     * The .zip project-export page: project picker + include-images toggle.
     */
    public function exportProject(Request $request): View
    {
        $projects = $request->user()->projects()->orderBy('name')->get();

        return view('admin.data.export-project', [
            'projects' => $projects,
        ]);
    }

    /**
     * The EPUB export page. For now (task 03) this only hosts the existing
     * download form moved out of the old tabbed index — the configuration form
     * itself (project settings, toggles, ordering) lands in task 04.
     */
    public function exportEbook(Request $request): View
    {
        $projects = $request->user()->projects()->orderBy('name')->get();

        return view('admin.data.export-ebook', [
            'projects' => $projects,
        ]);
    }

    /**
     * The import page: upload form + the global ImportSetting singleton (size
     * cap + background toggle) + the user's still-in-progress imports.
     */
    public function import(Request $request): View
    {
        // Only imports that are NOT completed are actionable (resume/discard);
        // a completed import has nothing left to show here.
        $imports = $request->user()->imports()
            ->where('phase', '!=', ImportPhase::Completed)
            ->latest()
            ->get();

        return view('admin.data.import', [
            'importSetting' => ImportSetting::current(),
            'imports' => $imports,
        ]);
    }
}
