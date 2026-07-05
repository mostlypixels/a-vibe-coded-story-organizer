# 02 — Owner share management (controller, routes, Form Request)

## Scope

The authenticated owner-facing endpoints that create and revoke a scene's share link.

**Builds:**
- `App\Http\Controllers\SceneShareController`:
  - `store(StoreSceneShareRequest $request, Scene $scene)` — authorize
    `update` on `$scene->chapter->act->project`; set `share_token = Str::random(48)` and
    `share_expires_at = now()->add($validated['duration'])`; redirect back to
    `route('scenes.edit', $scene)`. Re-invocation **rotates** the token (regenerate).
  - `destroy(Scene $scene)` — authorize the same; null out `share_token` + `share_expires_at`;
    redirect to `route('scenes.edit', $scene)`.
- `App\Http\Requests\StoreSceneShareRequest`:
  - `authorize()`: `$this->user()->can('update', $this->route('scene')->chapter->act->project)`.
  - `rules()`: `'duration' => ['required', Rule::in(array_values(config('sharing.scene_link_durations')))]`.
- Routes inside the existing `Route::middleware('auth')->group(...)`, next to the scene routes:
  - `POST /scenes/{scene}/share` → `scenes.share.store`.
  - `DELETE /scenes/{scene}/share` → `scenes.share.destroy`.

**Does NOT build:** the public view/route (task 03) or the edit-page UI that posts to these
routes (task 04). Tests here hit the routes directly.

## Depends on

- **01** (columns, config, `isShared()`) in `plan/implemented/`.

## Key decisions already made

- Authorization walks up to the project via `ProjectPolicy`; mirror it in the Form Request.
- Duration is validated against the `config('sharing.scene_link_durations')` whitelist.
- Token is `Str::random(48)`, set explicitly (not mass-assigned). Regenerate rotates it.
- Token-generation logic stays **inline in the controller** — no `SceneShareService` yet (single
  caller). See `../architecture.md` "Where the logic lives".

## Consult

`../architecture.md` (controllers, routes, authorization table), `00-overview.md`.

## Tests (`tests/Feature/SceneShareTest.php`)

- Owner POSTs with a valid duration → redirect; `$scene->fresh()->share_token` non-null and
  `share_expires_at` matches the chosen duration (assert it's within a minute of `now()->add(...)`).
- Invalid/absent duration → `assertSessionHasErrors('duration')`, token stays null.
- Non-owner POST → 403; token unchanged. Guest (no `actingAs`) → redirect to login.
- Regenerate: POST twice → token changes; capture the first token for task 03's "old URL 404s".
- Owner DELETE with an active link → token/expiry null. Non-owner DELETE → 403, unchanged.
