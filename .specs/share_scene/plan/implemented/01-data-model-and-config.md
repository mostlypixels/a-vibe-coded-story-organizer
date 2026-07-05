# 01 — Data model & config

## Scope

Foundation only — the schema, model state, and configuration the later tasks build on.

**Builds:**
- Migration `add_share_columns_to_scenes_table`:
  - `share_token` — `string(64)->nullable()->unique()->after('event_id')`.
  - `share_expires_at` — `timestamp()->nullable()->after('share_token')`.
- `App\Models\Scene`:
  - `casts`: add `'share_expires_at' => 'datetime'`.
  - **Do not** add either column to `$fillable` (token is never mass-assigned).
  - `isShared(): bool` — `share_token` set AND `share_expires_at` set AND `->isFuture()`.
  - `shareUrl(): ?string` — `route('shared.scenes.show', $this->share_token)` when a token
    exists, else `null`. (The route is defined in task 03; the helper only builds the URL by name,
    so it can be written now — its test runs once task 03 registers the route, or use
    `route(...)` which resolves lazily. If ordering bites, cover `shareUrl()` in task 03 instead.)
- `config/sharing.php`:
  - `'scene_link_durations'` — the whitelist the owner picks from, e.g.
    `['24 hours' => '24 hours', '7 days' => '7 days', '30 days' => '30 days']` (label ⇒
    `CarbonInterval`-parseable value). Keep the value format consistent and documented.
  - Optionally `'scene_link_default_duration'` — which whitelist key is preselected.

**Does NOT build:** the controllers, routes, Form Request (task 02), any view (tasks 03/04).
No generation logic here — computing `now()->add(...)` lives in the controller (task 02).

## Depends on

Nothing (first task).

## Key decisions already made

- Stored-token model, columns on `scenes`, token not fillable — see `00-overview.md`.
- Durations come from config (owner-picks-per-share), never hard-coded.

## Consult

`../data-model.md` (columns, helpers, config), `00-overview.md` (binding decisions).

## Tests (`tests/Feature/SceneShareTest.php` or a model test)

- `isShared()` is **true** only when token set and expiry in the future; **false** when token is
  null, and **false** when expiry is in the past. Build scenes via factory.
- `shareUrl()` returns `null` when unshared and the expected `route('shared.scenes.show', $token)`
  when a token is set (defer to task 03 if the route isn't registered yet).
- The migration adds the columns (a scene can be persisted with a token + expiry; the `unique`
  index rejects a duplicate token).
- Confirm `share_token` is not mass-assignable (`Scene::create([... 'share_token' => 'x'])` does
  not set it).

> [!NOTE]
> Check whether `ActFactory`/`ChapterFactory`/`SceneFactory` exist; add any missing ones here so
> tasks 02–04 can build the project→act→chapter→scene graph. This starts filling the documented
> Scene test-coverage gap.
