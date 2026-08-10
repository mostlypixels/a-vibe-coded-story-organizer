# Duplicate entities — testing

Extend `tests/Feature/SceneTest.php` and `tests/Feature/CodexEntryTest.php` rather than adding a
`DuplicateTest` — that is where each entity's position and authorization cases already live. The
name algorithm gets its own unit test.

## Both entities

* Owner posts `duplicate` with a name → 302 to the copy's edit page; a second row exists with the
  submitted name, in the same parent.
* Non-owner → 403, nothing created.
* Missing / blank / >255-char `name` → `assertSessionHasErrors('name')`, nothing created.
* A name that collides with an existing entity is **accepted** (the deliberate absence of a
  uniqueness rule — assert the row is created).
* The original is untouched: its own fields and its owned rows still belong to it.

## Scene

* Duplicate the middle of three siblings → the copy sits at `original.position + 1`, the former
  next sibling shifted to `+2`; ordering by `(position, id)` reads original, copy, rest.
* Duplicate the last sibling → the copy is last, nothing else moves.
* Sibling scope holds: duplicating a scene in chapter A never renumbers chapter B's scenes.
* `share_token` is null on the copy even when the original is shared.
* `word_count` on the copy equals the original's (recomputed by the `saving` hook, not copied).
* `status`, `contents` and `notes` are carried over verbatim.
* `event_id` and the `event_scene` rows are replicated, and `Event::count()` is unchanged.
* `scene_codex_entry` for the copy is rebuilt from its contents, not copied.

## Codex entry

* Aliases, `codex_media` rows and `codex_attribute_values` are **new rows** on the copy; the
  originals' rows still point at the original.
* Tag pivots point at the same `tags` rows — assert `Tag::count()` did not change.
* Media files are copied to different paths and both files exist (`Storage::fake('public')`).
  Deleting the original leaves the copy's file on disk — the regression this feature is most
  likely to introduce.
* A metadata-only media row (`path === null`) duplicates as `path === null` without throwing.
* An entry with no media / no aliases / no attribute values duplicates cleanly.

## Failure path

Force the transaction to throw after the files are copied (a mocked `SceneReferenceMatcher`, or a
deliberately failing insert) and assert no copied file survives — the `catch` cleanup in
`CodexEntryDuplicator`.

## Name suggestion (unit)

`tests/Unit/DuplicateNameTest.php`

| Input | Taken | Expected |
|---|---|---|
| `Arrival` | — | `Arrival (2)` |
| `Arrival` | `Arrival` | `Arrival (2)` |
| `Arrival` | `Arrival`, `Arrival (2)` | `Arrival (3)` |
| `Arrival (2)` | `Arrival (2)` | `Arrival (3)` |
| `arrival` | `ARRIVAL` | `arrival (2)` |
| `Arrival (2)` | — | `Arrival (2)` |

The last row pins the base-stripping rule: a free suffixed name is still re-suffixed from its
base, so the result is the lowest free number rather than the source name unchanged.

## Views

`BladeComponentCompilationTest` picks up the new `x-duplicate-dialog` automatically. Assert both
index pages and both edit pages render the trigger.
