<?php

namespace App\Http\Middleware;

use App\Support\RouteProject;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records the last project page the user successfully loaded on
 * `users.active_project_id`, so the navigation can keep showing that project on
 * pages that carry no project in their URL (the dashboard, /profile, /admin/*).
 *
 * Registered on the auth group in routes/web.php — guests have nothing to
 * track, and the public share/robots routes deliberately live outside `auth`.
 *
 * > [!WARNING]
 * > The write happens AFTER $next($request) and only for a 2xx response. That
 * > ordering is the authorization check: by the time a response exists the
 * > controller's authorize('view', ...) has already run, so a 403 or 404 can
 * > never park a foreign project in the picker. Moving this to the way in
 * > re-introduces exactly that bug — and no ownership comparison here would be
 * > an adequate substitute, because the status code stays correct even if the
 * > policy changes.
 */
class TrackActiveProject
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 1. Rejected, redirected or errored requests never write. This is the
        //    post-authorization gate described above.
        if (! $response->isSuccessful()) {
            return $response;
        }

        $user = $request->user();

        // 2. A page with no project in its route (dashboard, profile, admin)
        //    does nothing — it must never CLEAR the column. That is what makes
        //    the active project persist across a settings detour.
        $project = RouteProject::resolve($request);

        if ($user === null || $project === null) {
            return $response;
        }

        // 3. Already the active project: no UPDATE. One write on entering a
        //    project, not one per page inside it. Non-GET requests inside a
        //    project redirect (302, so step 1 skips them) and the GET that
        //    follows does the write — no method check is needed.
        if ($user->active_project_id === $project->id) {
            return $response;
        }

        // Assigned directly, not mass-assigned: active_project_id is kept out of
        // User::$fillable so this middleware is the only thing that can set it.
        $user->active_project_id = $project->id;
        $user->save();

        return $response;
    }
}
