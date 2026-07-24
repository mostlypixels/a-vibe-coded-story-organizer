<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;

/**
 * The project-scoped revisions browser (Tools ▸ Revisions): a landing page whose
 * sidebar lists every entity and field in the project that has revision history.
 *
 * The sidebar tree itself is built by the shared <x-revisions-layout> shell (via
 * App\Services\ProjectRevisionsBrowser), which the per-field history and compare
 * views (RevisionController) reuse — so this controller only resolves and
 * authorizes the project, then renders the landing pane. Thin, per CLAUDE.md.
 */
class RevisionBrowserController extends Controller
{
    /**
     * Show the browser landing page for one project.
     *
     * Authorizes `view` — reading revision history is a view capability, the
     * same altitude RevisionController's history / compare reads use
     * (RevisionController::resolve). The mutating revert / purge actions
     * instead demand `update`, so the browser and every read it links to gate
     * identically, one level below the writes.
     */
    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        return view('revisions.browser', ['project' => $project]);
    }
}
