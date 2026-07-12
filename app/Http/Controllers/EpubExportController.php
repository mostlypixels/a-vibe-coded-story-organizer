<?php

namespace App\Http\Controllers;

use App\Exceptions\EpubExportException;
use App\Http\Requests\EpubExportRequest;
use App\Models\Project;
use App\Services\EpubExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Exports a project to a downloadable .epub from Admin -> Export & import.
 *
 * Thin: resolve -> authorize -> delegate -> respond, mirroring
 * {@see ExportController}. The heavy lifting (tree filtering, XHTML rendering,
 * packaging, structural validation) lives in the HTTP-agnostic EpubExporter.
 *
 * The one user-facing failure — a project with nothing to export — surfaces from
 * the service as an EpubExportException; it is caught here and turned into a
 * redirect-back-with-error rather than being allowed to bubble as a 500. Every
 * other failure inside EpubExporter is a generator bug and is left to 500/log.
 */
class EpubExportController extends Controller
{
    public function store(EpubExportRequest $request, EpubExporter $exporter): BinaryFileResponse|RedirectResponse
    {
        // The admin gate is "any authenticated user"; authorize ownership too so a
        // foreign project_id 403s (mirrors EpubExportRequest::authorize()). project_id
        // is validated as an existing id, so findOrFail is a belt-and-braces guard.
        $project = Project::findOrFail($request->integer('project_id'));
        $this->authorize('view', $project);

        try {
            $epubPath = $exporter->export($project);
        } catch (EpubExportException $exception) {
            // "Nothing to export" is a user problem (no scenes yet), not a bug —
            // send them back to the form with the message rather than a 500.
            return redirect()->back()->withErrors(['project_id' => $exception->getMessage()]);
        }

        $filename = Str::slug($project->name).'-'.now()->format('Ymd-His').'.epub';

        // deleteFileAfterSend cleans up the temp epub once the response is streamed.
        return response()
            ->download($epubPath, $filename, ['Content-Type' => 'application/epub+zip'])
            ->deleteFileAfterSend(true);
    }
}
