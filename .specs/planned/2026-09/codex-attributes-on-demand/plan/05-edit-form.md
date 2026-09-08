---
title: "Task 05 — Edit form"
---

# Task 05 — Edit form

## Scope

`CodexEntryController::edit()`, `resources/views/codex/partials/attribute-timeline.blade.php`,
and the codex feature documentation.

Does **not** change the read pages, the create form, or `AttributeTimeline`.

## Depends on

Tasks 01 and 04.

## Key decisions already made

* `edit()` passes `sheets` => `$sheets->attached(...)` and adds
  `unattachedAttributes` => `$sheets->unattachedFor($codexEntry)`. `show()` is unchanged.
* The partial's `@if ($sheets->isNotEmpty())` guard **goes**. The card must render for an
  entry with zero attached attributes, or the attach form is unreachable. Empty state: one
  muted line, then the picker.
* Per attribute block, beside the `<h3>`: an `x-icon-delete-button` posting
  `codex.attributes.detach`, confirmed with wording that names the loss —
  *"Remove Hair colour from Melusine? Its values are deleted. The attribute stays in the
  project."*
* Below the blocks: one attach form, `x-select` of `$unattachedAttributes` + `x-button` Add,
  posting `codex.attributes.attach`. The select needs an `sr-only` label, like the existing
  "Add period at…" one.
* No delete button on the Start baseline. Detach is the only detach path.
* The muted discoverability line, linking `projects.codex-attributes.index`, appears here too.
* The `@foreach` body — baseline form, period forms, "Add period at…" — is otherwise unchanged.
* `documentation/features/codex.md` → *Temporal attributes*: add the two predicates and what
  a row's presence means. One short subsection, not a rewrite.

Detail: `expanded/ui.md` → *edit form*, *Discoverability*, *Controller view data*.

## Tests to add

Extend `tests/Feature/CodexEntryTest.php`:

* An entry with three attached attributes renders three timeline blocks, not every project
  attribute.
* An entry with zero attached attributes still renders the card and the attach form.
* Attaching via the route then loading edit shows the new block — assert on the response.
* The show page hides an attribute whose every row is blank.
* The Start-baseline guard in `codex.attribute-values.destroy` still returns 403 when other
  periods exist.
