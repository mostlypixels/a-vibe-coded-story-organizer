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
     * Authorizes `update` on the project — the same ability RevisionController's
     * own history / compare / revert actions check (RevisionController::resolve),
     * so the browser and every page it links to gate identically.
     */
    public function index(Project $project): View
    {
        $this->authorize('update', $project);

        return view('revisions.browser', ['project' => $project]);
    }
}
