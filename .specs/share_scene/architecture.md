# Share scene — Architecture

## Routes

Two audiences: **public** (no auth) for viewing, **owner** (auth) for managing the link.

`routes/web.php`

```php
// PUBLIC — outside the auth group, gated only by the token. noindex in the view.
Route::get('/shared/scenes/{token}', [SharedSceneController::class, 'show'])
    ->name('shared.scenes.show');
```

```php
// OWNER — inside the existing Route::middleware('auth')->group(...), next to the scene routes.
Route::post('/scenes/{scene}/share',   [SceneShareController::class, 'store'])->name('scenes.share.store');
Route::delete('/scenes/{scene}/share', [SceneShareController::class, 'destroy'])->name('scenes.share.destroy');
```

- The public route sits **outside** the `auth` group (the only unauthenticated app route besides
  `welcome` and Breeze auth). Everything else stays authenticated — do not widen the group.
- `{token}` binds as a plain string, resolved manually in the controller (not route-model
  binding) so an unknown/expired token produces the response we choose, not a generic 404 from
  the binder.
- Manage routes are flat on `{scene}` (implicit binding), matching the shallow scene routes
  (`scenes.edit`, `scenes.move-up`, …).

## Controllers

### `App\Http\Controllers\SharedSceneController` (public)

```php
public function show(string $token): View
{
    $scene = Scene::where('share_token', $token)
        ->with('chapter.act')          // for the "Chapter n — title: scene" heading
        ->first();

    abort_if($scene === null, 404);
    abort_unless($scene->isShared(), 410);   // token present but expired → 410 Gone

    return view('shared.scenes.show', ['scene' => $scene]);
}
```

- **No `authorize()` call** — this endpoint is intentionally public; the token *is* the
  authorization. Document that explicitly in a comment so the "every action authorizes"
  guideline reviewer understands the exception.
- Renders on a minimal public layout (see `ui.md`), not `x-app-layout` (no nav, no user).
- Reads only `name`, `description`, `contents`, and the chapter/act titles — **never `notes`**.

### `App\Http\Controllers\SceneShareController` (owner)

```php
public function store(Request $request, Scene $scene): RedirectResponse
{
    $this->authorize('update', $scene->chapter->act->project);

    $scene->update([
        'share_token'      => Str::random(48),
        'share_expires_at' => now()->add(config('sharing.scene_link_ttl')),
    ]);

    return redirect()->route('scenes.edit', $scene);
}

public function destroy(Scene $scene): RedirectResponse
{
    $this->authorize('update', $scene->chapter->act->project);

    $scene->update(['share_token' => null, 'share_expires_at' => null]);

    return redirect()->route('scenes.edit', $scene);
}
```

- Authorization walks up to the project via `ProjectPolicy@update` — the established pattern
  (`SceneController` does `$this->authorize('update', $scene->chapter->act->project)`).
- `store` is idempotent-ish: calling it again **regenerates** (rotates) the token and resets
  expiry, invalidating the previous URL. If "extend without rotating" is wanted, that's an open
  question.
- No Form Request needed (no user input beyond the route model). If a user-chosen duration is
  added later (open question), introduce `StoreSceneShareRequest` with a whitelist of allowed
  durations validated via `Rule::in(...)`.

## Where the logic lives

- **Token + expiry computation** is a one-liner used in exactly one place (`store`). Per the
  guidelines ("do not add abstraction before there is a second caller"), keep it inline in the
  controller — do **not** create a `SceneShareService` yet. If a second caller appears (e.g. a
  bulk-share action or an API), extract `App\Services\SceneShareService::issue(Scene): void`.
- **State queries** (`isShared`, `shareUrl`) live on the model as lifecycle/state helpers
  (allowed exception in guidelines), keeping controllers and Blade thin.
- **Expiry duration** lives in `config/sharing.php` — single source, no magic string.

## Authorization summary

| Action | Auth? | Check |
|--------|-------|-------|
| `shared.scenes.show` | No | Token validity only (no policy) |
| `scenes.share.store` | Yes | `ProjectPolicy@update` via `scene.chapter.act.project` |
| `scenes.share.destroy` | Yes | same |

## Rendering pipeline (public page)

- **Contents** → `Str::markdown($scene->contents ?? '')` echoed with `{!! !!}`, identical to
  `story/index.blade.php` line 94. Contents is Markdown-only (never HTML-sanitized), so this is
  the correct and only render path.
- **Description** → `<x-rich-text :html="$scene->description" />`, which already guards with
  `{!! !!}` on sanitized HTML (safe by construction). Wrapped in the collapsible card.
- **Title** → `Chapter {$scene->chapter->position} — {$scene->chapter->name}: {$scene->name}`
  (Arabic `position`, matching the rest of the app).

## Security notes

- Token entropy: `Str::random(48)` (48 alnum chars) is non-guessable; the `unique` index makes
  collisions a non-issue.
- Add `<meta name="robots" content="noindex, nofollow">` to the public layout head so shared
  links are not indexed if forwarded/posted.
- Expired tokens are rejected server-side (`isShared()`), so a leaked-but-old URL is inert.
- No CSRF concern on the public GET; the manage routes are POST/DELETE with the standard CSRF
  token from the authenticated form.
