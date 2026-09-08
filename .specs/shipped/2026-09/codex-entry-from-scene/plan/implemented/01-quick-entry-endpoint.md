---
title: "Task 01 — Quick entry endpoint"
---

# Task 01 — Quick entry endpoint

## Scope

The server half: one JSON endpoint that creates a name-and-type codex entry, resyncs the
one scene, and answers with the entry plus the scene's refreshed reference list as rendered
HTML.

Does **not** touch the scene view, the editor, or any JavaScript — tasks 03 and 04.

## Depends on

Nothing.

## Key decisions already made

* Route `POST /scenes/{scene}/codex-entries`, name `scenes.codex-entries.store`, bound to
  the **scene** — it is what gets resynced and what the response describes. Shallow, beside
  the other `/scenes/{scene}` routes.
* `SceneCodexEntryController::store()` — resolve, authorize, delegate, return JSON, the
  shape of `FieldAutosaveController`. It calls `CodexEntrySaver::create()` then
  `SceneReferenceMatcher::syncScene($scene)`, in that order.
* `CodexEntrySaver::create()` gains `bool $rescanProject = true` as its last parameter.
  When false it skips `syncProject()`. Comment why: a project-wide rescan is the cost this
  endpoint exists to avoid. Existing callers are unchanged.
* `CodexMediaUploads`'s constructor already defaults every argument, so `new CodexMediaUploads`
  is the blank instance. Do not add an `empty()` named constructor.
* `StoreQuickCodexEntryRequest`: `authorize()` is `can('update', …)` on the scene's project;
  `name` required/string/max:255/trimmed; `type` required + `Rule::enum(CodexEntryType::class)`.
  `withValidator()` refuses a name that case-insensitively equals an existing entry of the
  same type in the project, and the message carries that entry's id so the client can offer
  *Open it*. Entry counts are small — a plain collection compare is fine. Say in the
  docblock why this refuses where the full form only warns.
* The reference list moves into its own Blade partial, rendered by both the scene page and
  this endpoint. Blade stays the only template for it.
* Response: the created entry (`id`, `name`, `type`, `type_label`, `url`) plus the rendered
  list HTML.
* Create this feature's `standing-issues.md` recording the skipped project rescan: a new
  name does not match *other* scenes until each next saves or the resync command runs.

Detail: `expanded/architecture.md`, `expanded/overview.md` → *The cost nobody has priced*.

## Tests to add

`tests/Feature/SceneCodexEntryTest.php`:

* Creates the entry with the posted name and type; the response carries its id and url.
* Scene `updated_at`, `contents` and `word_count` are unchanged.
* The returned list includes the new entry when the stored contents hold the name, and
  excludes it when they do not.
* `syncProject()` does not run — another scene elsewhere in the project holding the same
  name keeps its pivot rows. Name the test so it reads as accepted debt, not a defect.
* Duplicate name, same type → 422 carrying the existing entry id. Same name, different type
  → allowed.
* Non-owner → 403. A scene from another project → 403, not an accidental 404.
* Blank, whitespace-only, and over-255 names; an unknown type.
* A quick-created entry has zero `codex_attribute_values` rows.

Regression: the normal codex create form still rescans the project through the default
parameter.
