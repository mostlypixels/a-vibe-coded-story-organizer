---
title: "Task 03 — Create form picker"
---

# Task 03 — Create form picker

## Scope

The create path: `CodexEntrySaver::seedAttributeBaselines()` and the create branch of
`resources/views/codex/partials/fields.blade.php`.

Does **not** touch the edit form, the attribute timeline partial, or the attach/detach
routes — tasks 04 and 05.

## Depends on

Nothing.

## Key decisions already made

* `seedAttributeBaselines()` stops looping the project's attributes. It iterates the
  **posted** `attribute_baselines` keys and writes a row for each, through
  `AttributeTimeline::ensureBaseline()`.
* **A blank posted value still writes a row.** Only picked attributes are posted, so a blank
  key means the writer picked it and has not filled it yet — the same meaning attach carries
  on the edit form. Note this supersedes `expanded/testing.md`'s "posting a blank baseline
  writes no row".
* Drop the `codexAttributesFor($type)` loop entirely. `StoreCodexEntryRequest::withValidator()`
  already rejects a foreign-project or wrong-type attribute id; one guard, in the Form Request.
* The create form's Attributes card becomes a picker card, Alpine only, no round-trip: each
  pick appends a labelled `attribute_baselines[{id}]` text input with a Remove beside it.
  Options are `$attributes` (already passed by `create()`) minus the picks.
* Collapsed to one line when nothing is picked; hidden when the type has no attributes.
* Restore picks from `old('attribute_baselines')` after a validation failure.
* Reuse `x-select` + `x-button`, not `x-chip-picker`.
* One muted line under the picker linking `projects.codex-attributes.index`.
* Each generated input gets a real, visible `<label>`.

Detail: `expanded/ui.md` → *create form*, `expanded/architecture.md` → *`CodexEntrySaver`*.

## Tests to add

Extend `tests/Feature/CodexEntryTest.php`:

* Posting two `attribute_baselines` on a project with five attributes writes two rows, not
  five — **fails before this change**.
* Posting a picked-but-blank baseline writes one `''` row.
* The create form response contains no attribute value input.
* The existing `withValidator` cases still reject a foreign-project and a wrong-type id.
