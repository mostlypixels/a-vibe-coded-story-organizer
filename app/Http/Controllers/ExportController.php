<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportRequest;
use App\Models\Project;
use App\Services\StaticSiteExporter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Exports a project to a downloadable .zip from Admin -> Export & import.
 *
 * Thin: resolve -> authorize -> delegate -> respond. The heavy lifting lives in
 * StaticSiteExporter (an HTTP-agnostic, async-ready service).
 */
class ExportController extends Controller
{
    public function store(ExportRequest $request, StaticSiteExporter $exporter): BinaryFileResponse
    {
        // The admin gate is "any authenticated user"; authorize ownership too so a
        // foreign project_id 403s (mirrors ExportRequest::authorize()). project_id
        // is validated as an existing id, so findOrFail is a belt-and-braces guard.
        $project = Project::findOrFail($request->integer('project_id'));
        $this->authorize('view', $project);

        $zipPath = $exporter->export(
            $project,
            $request->boolean('include_images'),
            $request->boolean('include_revisions')
        );

        $filename = Str::slug($project->name).'-'.now()->format('Ymd-His').'.zip';

        // deleteFileAfterSend cleans up the temp zip once the response is streamed.
        return response()
            ->download($zipPath, $filename, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }
}
