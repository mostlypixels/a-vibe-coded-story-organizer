# 02 — Scene duplication (backend)

**Depends on:** 01.

## Scope

* `POST /scenes/{scene}/duplicate` → `scenes.duplicate`, beside the existing `move-up`/`move-down`
  routes.
* `App\Http\Requests\DuplicateEntityRequest` — shared with task 04. Authorizes via
  `App\Support\RouteProject`, so it needs no per-entity subclass.
* `HasSiblingPosition::makeRoomAfter(): int` — shifts later siblings down by one, returns the
  freed position. Runs inside the caller's transaction.
* `App\Services\SceneDuplicator::duplicate(Scene $scene, string $name): Scene`.
* `SceneController::duplicate()`.

Not in scope: any Blade change (task 03), the codex route or duplicator (task 04).

## Key decisions

* The Form Request is the **only** authorization check — no second `$this->authorize()` in the
  action, since nothing runs before validation.
* Copied: `description`, `contents`, `notes`, `status`, `chapter_id`, `event_id`, and the
  `event_scene` pivot rows. Never copied: `share_token`, `share_expires_at`. `word_count` is left
  to the existing `saving` hook.
* Position: `original.position + 1` with later siblings shifted — not `max(position) + 1`.
* `SceneReferenceMatcher::syncScene($copy)` inside the transaction, mirroring
  `SceneController::store`.
* Redirect to `scenes.edit` on the copy with `->with('status', 'duplicated')`.
* `makeRoomAfter()` belongs in the trait, not the service: it needs `siblingScopeColumn()`, and
  the trait is already the one home of sibling position arithmetic.

## Consult

`expanded/architecture.md` → *Routes*, *Form Request*, *SceneDuplicator*, *Position insertion*;
`expanded/data-model.md` → *What each duplicate copies*, *Position*.

## Tests

Extend `tests/Feature/SceneTest.php` with the *Both entities* and *Scene* sections of
`expanded/testing.md`: owner happy path, non-owner 403, name validation failures, an accepted
colliding name, the three position cases, the unshared copy, `word_count`, the replicated event
links with `Event::count()` unchanged, and the rebuilt (not copied) `scene_codex_entry`.
