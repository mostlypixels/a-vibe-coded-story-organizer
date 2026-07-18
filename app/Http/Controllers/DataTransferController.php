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
     * The EPUB export page: the config form (task 04) for one selected project
     * plus the existing download form. The project picker reloads this page via
     * a plain GET (?project=) rather than JavaScript — selecting a project loads
     * ITS saved PublicationSetting (or an unsaved default when it never visited
     * this form). Falls back to the user's first project when none is selected
     * or the query value doesn't resolve to one of their own projects.
     */
    public function exportEbook(Request $request): View
    {
        $projects = $request->user()->projects()->orderBy('name')->get();

        $selectedProject = $projects->firstWhere('id', (int) $request->query('project'))
            ?? $projects->first();

        return view('admin.data.export-ebook', [
            'projects' => $projects,
            'selectedProject' => $selectedProject,
            'setting' => $selectedProject?->publicationSettingOrDefault(),
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
