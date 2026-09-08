---
title: "Task 04 — Attach and detach routes"
---

# Task 04 — Attach and detach routes

## Scope

A new `CodexEntryAttributeController`, a new `AttachCodexAttributeRequest`, and two routes.
Attaching and detaching an attribute is a different resource from one anchored value, so it
does not belong in `CodexAttributeValueController`.

Does **not** render anything — task 05 builds the forms that call these.

## Depends on

Nothing, but task 05 depends on it.

## Key decisions already made

| Verb | URI | Name |
|---|---|---|
| POST | `/codex/{codexEntry}/attributes` | `codex.attributes.attach` |
| DELETE | `/codex/{codexEntry}/attributes/{codexAttribute}` | `codex.attributes.detach` |

Shallow, matching the existing attribute-value routes. Both redirect to `codex.edit`.

* Attach calls `AttributeTimeline::ensureBaseline('')` for the posted attribute — idempotent,
  so a repeat post makes no duplicate.
* Detach is a plain `delete()` on
  `$entry->attributeValues()->where('codex_attribute_id', …)`. It bypasses
  `AttributeTimeline::removeAt()` **on purpose**: that method guards the Start baseline
  against leaving a hole, and detaching removes the whole pair, so there is no hole to
  leave. Say so in a comment.
* `abort_unless($codexAttribute->project_id === $codexEntry->project_id, 404)` on both.
* `AttachCodexAttributeRequest`: `authorize()` is `can('update', …->project)`;
  `codex_attribute_id` required/integer/`Rule::exists` scoped to the project; the entry-type
  check goes in `withValidator()` via `CodexAttribute::appliesTo()`, so the picker gets a
  validation error rather than a 422 abort.

Detail: `expanded/architecture.md` → *New controller*, *New Form Request*.

## Tests to add

`tests/Feature/CodexEntryAttributeTest.php`:

* Attach creates exactly one Start-anchored `''` row.
* Attach twice makes no duplicate.
* Attach of another project's attribute → 404.
* Attach of an attribute that does not apply to the entry's type → validation error.
* Detach deletes all of that pair's rows, mid-timeline periods included.
* Detach leaves the `codex_attributes` row and other entries' values alone.
* Non-owner → 403 on both. Guest → redirect to login.
