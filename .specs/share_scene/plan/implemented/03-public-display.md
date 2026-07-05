# 03 — Public display page (route, controller, layout, views)

## Scope

The unauthenticated, read-only page a visitor with a valid token sees, plus the expired state.

**Builds:**
- Route **outside** the `auth` group: `GET /shared/scenes/{token}` →
  `SharedSceneController@show`, name `shared.scenes.show`.
- `App\Http\Controllers\SharedSceneController@show(string $token)`:
  - `Scene::where('share_token', $token)->with('chapter.act')->first()`.
  - `abort_if($scene === null, 404)` (unknown token).
  - Expired (`! $scene->isShared()`) → render `shared/scenes/expired.blade.php` with status **410**
    (friendly branded page), **not** a bare `abort(410)`.
  - Otherwise render `shared/scenes/show`. **No `authorize()`** — comment that the token is the
    gate (the deliberate exception to "every action authorizes").
  - Read only `name`, `description`, `contents`, and chapter/act titles — **never `notes`**.
- `resources/views/layouts/public.blade.php` (`<x-public-layout>`): `@vite` assets, wide reading
  column (`max-w-3xl`), `<meta name="robots" content="noindex, nofollow">`, no nav, no user chrome.
- `resources/views/shared/scenes/show.blade.php`:
  - `<h1>`: `Chapter {chapter.position} — {chapter.name}: {scene.name}` (Arabic, em-dash).
  - Description in a **collapsed** Alpine card (`x-data="{ open:false }"`), body via
    `<x-rich-text :html="$scene->description" />`. Only when `filled($scene->description)`.
  - Contents: `<article class="prose ...">{!! Str::markdown($scene->contents ?? '') !!}</article>`,
    prose classes matching `story/index.blade.php`.
- `resources/views/shared/scenes/expired.blade.php`: same public layout, "This share link has
  expired." message. Returned with a 410 status.
- Add a short **"Scene sharing"** section to `documentation/architecture.md` (public token route,
  stored-token model, notes-are-private, the auth-group exception).

**Does NOT build:** the owner edit-page share UI (task 04) or the management endpoints (task 02).

## Depends on

- **01** (columns, `isShared()`, `shareUrl()`) in `plan/implemented/`. Tests seed a token directly
  via factory/`update()` — task 02 is **not** required.

## Key decisions already made

- Dedicated public layout with `noindex`; Arabic numbering + em-dash; collapsed description card;
  Markdown→HTML contents; `notes` excluded; unknown→404, expired→friendly 410 page. See
  `00-overview.md`.

## Consult

`../ui.md` (public page structure), `../architecture.md` (show action, rendering pipeline),
`00-overview.md`.

## Tests (`tests/Feature/SceneShareTest.php`)

- Valid unexpired token → 200; sees the chapter/act/scene title; Markdown contents rendered as
  HTML (e.g. `**x**` → `<strong>`).
- **`notes` never exposed**: give the scene distinctive notes; `assertDontSee` on the response.
- Description markup present in the response (collapse is client-side).
- Unknown token → 404. Expired token → 410 **and** sees the expired-page copy, `assertDontSee`
  the scene name (no data leak).
- No `actingAs` still returns 200 for a valid token (route is outside the auth group).
- The response head contains the `noindex` robots meta.
