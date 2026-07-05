<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSceneShareRequest;
use App\Models\Scene;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * Owner-facing management of a scene's public share link.
 *
 * The token + expiry computation is a one-liner used only here, so it stays
 * inline (no SceneShareService until a second caller appears). Both actions
 * authorize by walking up to the owning project via ProjectPolicy@update.
 */
class SceneShareController extends Controller
{
    /**
     * Generate (or regenerate) the scene's public share link.
     *
     * Re-invocation rotates the token and resets the expiry, invalidating the
     * previous URL. The token is set explicitly (never mass-assigned) — the
     * share_token / share_expires_at columns are deliberately not in $fillable.
     */
    public function store(StoreSceneShareRequest $request, Scene $scene): RedirectResponse
    {
        $this->authorize('update', $scene->chapter->act->project);

        $scene->share_token = Str::random(48);
        $scene->share_expires_at = now()->add($request->validated('duration'));
        $scene->save();

        return redirect()->route('scenes.edit', $scene);
    }

    /**
     * Revoke the scene's share link by clearing the token and expiry.
     */
    public function destroy(Scene $scene): RedirectResponse
    {
        $this->authorize('update', $scene->chapter->act->project);

        $scene->share_token = null;
        $scene->share_expires_at = null;
        $scene->save();

        return redirect()->route('scenes.edit', $scene);
    }
}
