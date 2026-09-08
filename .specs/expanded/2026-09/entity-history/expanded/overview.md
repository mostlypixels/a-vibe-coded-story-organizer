# Overview

## The problem, against the code

`AutosavableFields::REGISTRY` serves autosave: it maps a slug to a model, its rich-text
fields, validation rules, coalescing windows and character caps. Revision history was built
on top of it, so **a field is remembered only if it autosaves**.

`name`, `aliases` and `tags` are saved by the form, not by autosave. They are therefore
absent from the registry, absent from history, and absent from the field selector — which
offers exactly one option, Description.

Two consequences, both reported by a serial writer with published chapters:

- "When did I change her name?" has no answer. A rename is nowhere in the app.
- One save writes four rows — two `Baseline`, one `Saved`, one `Autosaved`, same minute,
  each reading "No summary recorded". The page shows the recorder's machinery instead of
  what the writer did.

## Goals

- Track the whole entity: `name`, `aliases`, `tags` beside the registered text fields.
- One timeline per entity. A save point is a moment, whatever it touched.
- Each row says what changed in words — "renamed Bertrand to Bertran", "added alias Tisi" —
  without opening a comparison.
- Collapse the rows one save produces into one save point.
- The empty state tells the truth.

## Non-goals

- **No edit-time history for codex attribute values.** They carry story-time history by
  design, and `AutosavableFields`' docblock states the prohibition outright. Edit-time
  history of a story-time value is a separate feature — see
  [open-questions.md](open-questions.md).
- No change to retention, pruning or purging.
- No change to when autosave writes. This is about what is recorded.
- No cross-entity or project-wide activity feed.
- No new revisionable entity. Only fields on entities that already have history.

## Scope: which entities

`name` exists on every revisionable entity; `aliases` and `tags` are `CodexEntry` only.
Tracking `name` everywhere is the same registry entry repeated, and the serial writer's
question ("when did I rename this chapter?") is not codex-specific.

**Decision: `name` on every entity already in the registry; `aliases` and `tags` on
`CodexEntry`.** `Project` has no `name`-less case worth special-handling.

## User stories

| As a | I want | So that |
| --- | --- | --- |
| Serial writer, published | to see when a character was renamed | I can find the continuity error in chapters already out |
| Serial writer | to see when an alias was added or dropped | I know which chapters predate it |
| Any writer | one row per save, in words | the page tells me what I did, not how it was stored |
| Any writer | an honest empty state | "no history" and "no matches" are different answers |

## Acceptance criteria

1. Changing an entity's `name` records a revision, and the history page lists it.
2. Adding, removing or renaming an alias or tag on a codex entry records a revision.
3. The field selector offers every tracked field, not only Description.
4. One save produces **one** save point, whatever mix of fields and origins it wrote.
5. Each save point's summary names the change in words. A rename reads "renamed X to Y";
   a set change reads "added alias Tisi", "removed tag draft".
6. `name`, `aliases` and `tags` gain **no** autosave endpoint — they are tracked, not
   autosaved. A POST to the autosave route for them 404s exactly as today.
7. Codex attribute values remain untracked by edit-time history.
8. Retention and pruning behave as today: `Automatic` rows prune, the rest survive.
9. A non-owner gets 403 on any history route, as today.
