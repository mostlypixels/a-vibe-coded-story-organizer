---
title: "Task 02 — Cleanup migration"
---

# Task 02 — Cleanup migration

## Scope

One migration that deletes every `codex_attribute_values` row belonging to an
(entry, attribute) pair whose values are **all** blank. Without it, every entry made
through the old create form keeps its fifteen boxes.

Does **not** change the importer (out of scope — old archives restore blank rows, accepted)
or any application code.

## Depends on

Nothing.

## Key decisions already made

* Use the **query builder**, not row-value tuple SQL: group by
  (`codex_entry_id`, `codex_attribute_id`), keep the groups where the longest trimmed value
  is empty, then delete those pairs' value rows. Portable and testable on the suite's
  in-memory SQLite.
* A pair with a blank Start but a filled later period is **kept** — that is a real
  "unknown until X" timeline, not leftovers.
* **No `down()`.** Pre-V1; the data being dropped is demo data.

Detail: `expanded/data-model.md` → *Existing data*.

## Tests to add

A feature test that builds a project with an all-blank pair and a project with a
blank-then-filled pair, runs the migration, and asserts the first pair's rows are gone and
the second's are intact. Assert the `codex_attributes` rows themselves survive.
