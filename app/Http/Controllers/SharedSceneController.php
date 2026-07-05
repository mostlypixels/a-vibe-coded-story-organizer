<?php

namespace App\Http\Controllers;

use App\Models\Scene;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * The public, unauthenticated view of a shared scene.
 *
 * This is the single deliberate exception to the app-wide "every action
 * authorizes through the owning Project" rule: there is intentionally NO
 * authorize() call here. The opaque `share_token` in the URL *is* the
 * authorization — anyone who holds a live token may read the scene. The route
 * lives OUTSIDE the `auth` middleware group so visitors need no account.
 */
class SharedSceneController extends Controller
{
    /**
     * Show a scene by its public share token.
     *
     * The token is bound as a plain string (not route-model binding) so we
     * control the response for the unknown/expired cases ourselves:
     *  - unknown token           → 404 (no such share)
     *  - expired/revoked token   → friendly branded 410 page (link is inert)
     *  - live token              → the read-only public scene page
     *
     * Only `name`, `description`, `contents`, and the chapter/act titles are
     * rendered — `notes` is private and never leaves the owner's screen.
     */
    public function show(string $token): View|Response
    {
        $scene = Scene::where('share_token', $token)
            ->with('chapter.act') // for the "Chapter n — chapter: scene" heading
            ->first();

        abort_if($scene === null, 404);

        // Never trust the mere presence of a token: isShared() also checks the
        // expiry is still in the future, so a leaked-but-expired URL is inert.
        if (! $scene->isShared()) {
            return response()->view('shared.scenes.expired', status: 410);
        }

        return view('shared.scenes.show', ['scene' => $scene]);
    }
}
