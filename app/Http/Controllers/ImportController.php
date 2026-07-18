<?php

namespace App\Http\Controllers;

use App\Exceptions\ImportValidationException;
use App\Http\Requests\ImportProjectRequest;
use App\Jobs\ProjectImportJob;
use App\Models\Import;
use App\Models\ImportSetting;
use App\Services\ProjectImporter;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Handles project imports from Admin -> Export & import.
 *
 * Thin: authorize -> delegate to {@see ProjectImporter} -> redirect. All the
 * validation, extraction, checkpointing, and graph-building logic lives in the
 * HTTP-agnostic service; this controller only translates its outcomes into
 * redirects and flash/error feedback.
 *
 * Whether an import runs inline within the request or is dispatched to the
 * queue depends on ImportSetting::current()->run_in_background, read live at
 * submit time (task 07). Validation (ProjectImporter::start()) is always
 * synchronous regardless of that toggle — only the graph-import phases (run())
 * are ever queued.
 */
class ImportController extends Controller
{
    public function __construct(private ProjectImporter $importer) {}

    /**
     * Validate and import an uploaded archive as a NEW project for the current
     * user.
     *
     * Two distinct failure surfaces (per the task's decided feedback contract):
     *  - start() rejecting the archive (ImportValidationException) is a form
     *    problem: redirect back with the message on the `archive` field.
     *  - run() failing mid-import has no `archive` field to attach to once past
     *    validation; the Import row already carries a safe `failure_message`
     *    (set by the service), so redirect to the Import tab with a generic flash.
     */
    public function store(ImportProjectRequest $request): RedirectResponse
    {
        // The `access-admin` gate is "any authenticated user"; there is no
        // existing Project to walk up to (a new one is created), so ownership is
        // simply "$request->user() is the sole owner" — the same exception
        // CrawlerSetting uses. See ImportProjectRequest::authorize().
        try {
            $import = $this->importer->start(
                $request->file('archive'),
                $request->user(),
            );
        } catch (ImportValidationException $exception) {
            // Structural validation failed before any row was written — surface
            // it as a field error the upload form can display.
            return back()->withErrors(['archive' => $exception->getMessage()]);
        }

        // Background mode is opt-in and read live here (the toggle can change
        // between attempts). When on, only the graph-import phases are deferred;
        // validation above already ran synchronously, so a bad archive never
        // reaches the queue.
        if (ImportSetting::current()->run_in_background) {
            // Mark the row as queued so the Import tab can distinguish "queued,
            // maybe still running" from "ran inline and crashed". A queue worker
            // (`php artisan queue:work`) must be running for it to progress.
            $import->update(['queued' => true]);

            ProjectImportJob::dispatch($import);

            return redirect()->route('admin.data.import.index')
                ->with('status', __('Import queued.'));
        }

        try {
            $this->importer->run($import);
        } catch (Throwable) {
            // The service already recorded a safe failure_message on the row and
            // left it resumable/discardable — the Import tab shows the details.
            return redirect()->route('admin.data.import.index')
                ->with('status', __('Import failed — see the Import tab for details.'));
        }

        return redirect()->route('projects.show', $import->project)
            ->with('status', __('Project imported.'));
    }

    /**
     * Resume a stalled import from its last committed checkpoint.
     *
     * Unlike store(), an Import row DOES have an owner by now, so this uses the
     * real ImportPolicy (ownership) — the one part of the feature that behaves
     * like every other owned resource, not like CrawlerSetting.
     */
    public function resume(Import $import): RedirectResponse
    {
        $this->authorize('resume', $import);

        // Follow the CURRENT toggle value, not whatever it was when the import
        // was first started — the setting may have changed between attempts.
        if (ImportSetting::current()->run_in_background) {
            $import->update(['queued' => true]);

            ProjectImportJob::dispatch($import);

            return redirect()->route('admin.data.import.index')
                ->with('status', __('Import queued.'));
        }

        try {
            $this->importer->run($import);
        } catch (Throwable) {
            // A repeated failure leaves the row resumable again with a fresh
            // failure_message; the Import tab surfaces it either way.
        }

        return redirect()->route('admin.data.import.index');
    }

    /**
     * Discard a stalled import: roll back its partial Project, delete the stored
     * archive/extraction, and remove the Import row. Owner-only (ImportPolicy).
     */
    public function destroy(Import $import): RedirectResponse
    {
        $this->authorize('discard', $import);

        $this->importer->discard($import);

        return redirect()->route('admin.data.import.index')
            ->with('status', __('Import discarded.'));
    }
}
