# Task 6 — `FieldAutosaveController` + routes + conflicts + coarse-trigger wiring

## Scope

The first real HTTP surface of this feature:

* `App\Http\Controllers\FieldAutosaveController::update()` — resolves the slug via
  `AutosavableFields`, authorizes via `HasRevisions::revisionProject()`, validates via
  the registry's rule, does the 409 base-hash conflict check, saves the model, decides
  whether to call `RevisionRecorder::record()` (skipping a byte-identical value),
  triggers `SceneReferenceMatcher::syncScene()` + fires `SceneContentsChanged` when the
  request flags a coarse trigger, and returns `{value, hash, revision_id, saved_at}`
  computed from the **stored** (post-mutator) value.
* `routes/web.php` additions: `PATCH /autosave/{entity}/{id}/{field}` (`autosave.
  update`), gated by `->whereIn('entity', AutosavableFields::slugs())` and
  `throttle:120,1`.
* The `App\Events\SceneContentsChanged` event class (fired, not yet subscribed to by
  anything — that's `.specs/draft/word-count`'s job).

Does **not** include: the history/compare/revert routes or controller (tasks 10–11 —
this task only adds `autosave.update`), the client JS, or any Blade changes.

## Depends on

Task 2 (widened columns — so large payloads don't silently truncate), task 3
(`AutosavableFields`, `HasRevisions`), task 4 (`RevisionRecorder`).

## Key decisions already made

* **The server is the sole hash authority** (`handoff.md` §9.13 — the concrete bug this
  prevents: if the client hashed what it *sent*, the second autosave of every rich-HTML
  field would 409 forever, because `SanitizesRichHtml`'s set-mutator changes the stored
  value). The response hash is always `hash('sha256', $storedValue)` computed *after*
  `$model->save()` and a fresh re-read, never a hash of the request payload.
* **409, not last-write-wins**, on a `base_hash` mismatch — compare the request's
  `base_hash` against `hash('sha256', $model->{$field})` **before** applying the write.
* **Byte-identical values write no revision** — compare the validated incoming value
  against the entity's *current* column value before calling `RevisionRecorder::
  record()`; if equal, skip (still return 200 with a fresh hash/timestamp — the save
  itself is a no-op, not an error).
* **Coarse vs. fine triggers are request flags the client sets**, not inferred
  server-side: a `run_matcher` boolean (blur/Ctrl-S/submit set it; a bare debounce tick
  omits it) gates `SceneReferenceMatcher::syncScene()` (scene contents only) and the
  `SceneContentsChanged` event; a separate `manual` boolean (set only by the real form
  Save action) forces `origin: manual` instead of `automatic`.
* **`manual=true` never coalesces** — this is what makes the existing full-form Save
  button's behavior visible in history as a distinct, permanent entry (`RevisionRecorder`
  already enforces this per task 4; this controller just passes the right origin
  through).
* **429 with `Retry-After`** comes from Laravel's built-in `throttle:120,1` middleware —
  no custom rate-limit logic needed.
* **Validation errors are 422**, using exactly `AutosavableFields::validationRule()` —
  no parallel rule set.

## Consult

* `expanded/architecture.md` — the full `FieldAutosaveController` code sketch and the
  routes block.
* `handoff.md` §2.4 (trigger table), §2.5 (matcher coarse-trigger rule), §3.2–§3.3
  (registry as security boundary, conflict design), §9.10 (`SceneContentsChanged` seam),
  §9.13 (hash authority).
* `app/Services/SceneReferenceMatcher.php` (already read this session) for
  `syncScene()`'s exact signature.

## Tests

`tests/Feature/FieldAutosaveTest.php`:

* Happy path PATCH on at least two different `FieldKind`s (one `Rich`, one `Markdown`)
  updates the live column and creates exactly one new revision.
* Non-owner gets 403 (CLAUDE.md's mandatory negative-authorization case).
* An entity slug not in `AutosavableFields::slugs()` 404s **at the router** — assert via
  a route-list check or a request to an unregistered slug, not a controller unit test.
* Validation failure (e.g. Markdown cap exceeded) returns 422 with the same rule the
  Form Request enforces elsewhere for that field.
* Correct `base_hash` → 200; stale `base_hash` → 409, live column left unchanged.
* Response `hash` matches `hash('sha256', <fresh column value>)`, not a hash of the
  request payload — construct a rich-HTML case where the sent value and the sanitized
  stored value differ, and assert the returned hash matches the *stored* value.
* Byte-identical save (same value as currently stored) → 200, but no new `revisions`
  row.
* `run_matcher=true` on a `Scene.contents` PATCH updates `scene_codex_entry`; the same
  PATCH without `run_matcher` does not.
* `run_matcher=true` on `Scene.contents` fires `SceneContentsChanged` (assert via
  `Event::fake()`); a bare debounce PATCH does not.
* `manual=true` always inserts a new `origin: manual` row, even when called twice in
  immediate succession (proving it bypasses coalescing).
* Exceeding `throttle:120,1` returns 429 with a `Retry-After` header.
