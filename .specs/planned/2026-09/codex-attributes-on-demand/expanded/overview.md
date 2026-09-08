# Overview

## Problem

The create form asks for every project attribute at once; the edit form keeps asking
forever. Both ignore what the entry actually holds.

Two facts found in code, not in the source spec:

- `CodexEntrySaver::seedAttributeBaselines()` writes an `''` baseline row for **every**
  applicable attribute on create. So a fresh entry already has fifteen rows.
- `CodexAttributeSheets::setOnly()` keeps a sheet when a **row exists**, not when a value
  is filled. `CodexAsOfResolver` uses `filled()`. The two rules already drifted.

Result: the read page also lists fifteen blank attributes for any entry made through the
form. The spec's "the read pages already do the right thing" is only true for
seeder-made entries. Fixing the rule is in scope; it is the same defect.

## Core decision

No schema change. The `codex_attribute_values` row carries both meanings:

| Question | Rule | Used by |
|---|---|---|
| Is this attribute **on this entry**? | any row exists for the (entry, attribute) pair | edit form |
| Does it have something **to show a reader**? | any row has `filled(value)` | show page, as-of panel |

An `''` row is therefore "attached but blank" — which answers the spec's
empty-but-pinned open end for free. Both predicates live on `CodexAttributeSheets`, so
they cannot drift again.

## Goals

- Create form: name, description, aliases, media, tags. No attribute boxes.
- Edit form: only attributes attached to the entry.
- One picker adds an attribute from the project's list for this entry type.
- One control detaches an attribute from the entry (project definition untouched).
- Removing the last period of a pair already detaches it — keep that.
- A line on both forms points at the project Attributes screen.

## Non-goals

Unchanged: `AttributeTimeline`, the timeline semantics, the project Attributes screen,
per-entry attribute definitions, the default bundle sets (`onboarding-codex-data`).

## Acceptance criteria

- New character form renders zero attribute inputs, with a picker offering the project's
  character attributes.
- Creating with two attributes filled writes two rows, not fifteen.
- Edit form for an entry with three attached attributes renders three timeline blocks.
- Adding via the picker attaches with a blank Start baseline and the block appears.
- Detaching deletes that pair's rows only; the `CodexAttribute` row survives.
- Show page hides an attribute whose every row is blank.
