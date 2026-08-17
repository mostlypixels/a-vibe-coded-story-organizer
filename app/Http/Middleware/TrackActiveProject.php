<?php

namespace App\Http\Middleware;

use App\Support\RouteContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records the last project page the user successfully loaded on
 * `users.active_project_id`, and the last book on that project's
 * `projects.last_book_id`, so the navigation can keep showing them on pages
 * that carry no project or book in their URL (the dashboard, /profile,
 * /admin/*, and — for the book — the timeline, the codex, the tools section).
 *
 * Registered on the auth group in routes/web.php — guests have nothing to
 * track, and the public share/robots routes deliberately live outside `auth`.
 *
 * > [!WARNING]
 * > Both writes happen AFTER $next($request) and only for a 2xx response.
 * > That ordering is the authorization check: by the time a response exists
 * > the controller's authorize('view', ...) has already run, so a 403 or 404
 * > can never park a foreign project or book in the picker. Moving this to
 * > the way in re-introduces exactly that bug — and no ownership comparison
 * > here would be an adequate substitute, because the status code stays
 * > correct even if the policy changes.
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

        $context = RouteContext::resolve($request);
        $user = $request->user();

        // 2. A page with no project in its route (dashboard, profile, admin)
        //    does nothing — it must never CLEAR the column. That is what makes
        //    the active project persist across a settings detour. Already the
        //    active project: no UPDATE — one write on entering a project, not
        //    one per page inside it. Non-GET requests inside a project
        //    redirect (302, so step 1 skips them) and the GET that follows
        //    does the write — no method check is needed.
        if ($user !== null && $context->project !== null && $user->active_project_id !== $context->project->id) {
            // Assigned directly, not mass-assigned: active_project_id is kept
            // out of User::$fillable so this middleware is the only thing
            // that can set it.
            $user->active_project_id = $context->project->id;
            $user->save();
        }

        // 3. Same rule one level down: a page with no book in its route (the
        //    timeline, the codex, the tools section) leaves last_book_id
        //    alone, so a detour out of the manuscript returns to the same
        //    book. A resolved book always carries its project, so this needs
        //    no separate null check on $context->project.
        if ($context->book !== null && $context->project->last_book_id !== $context->book->id) {
            // last_book_id is kept out of Project::$fillable the same way.
            $context->project->last_book_id = $context->book->id;
            $context->project->save();
        }

        return $response;
    }
}
