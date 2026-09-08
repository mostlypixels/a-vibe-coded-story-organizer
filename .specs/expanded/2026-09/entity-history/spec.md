---
status: expanded
expanded: 2026-09-08
---

# Entity History

History answers one question today: what did this description say before? Every other
fact about an entity is untracked. Rename a character and the rename is nowhere. The
field selector on a codex entry's history page offers exactly one option, Description.

A serial writer with published chapters asks the opposite question: "when did I change
her name?" and "when did I change her hair?" Neither is answerable. Her chapters are out
in the world, so a fact she changed six months ago is a continuity error she cannot find.

`AutosavableFields::REGISTRY` is the reason: it registers text fields for autosave, and
history rides on it. `name`, `aliases` and `tags` are not autosaved, so they are not
tracked.

One save also produces four rows — two `Baseline`, one `Saved`, one `Autosaved`, all in
the same minute, each reading "No summary recorded". The page shows the machinery rather
than the change.

## Goals

- Track the whole entity: name, aliases and tags beside the fields already registered.
- One timeline for an entity. A save point is a moment, whatever it touched.
- Each row says what changed in words — "renamed Bertrand to Bertran", "added alias
  Tisi" — without opening a comparison.
- Collapse the rows one save produces into one save point.
- The empty state tells the truth. "No history yet" when nothing was ever saved, and the
  filter message only when a filter is actually set.

## Non-goals

- No new history for codex attribute values. They have story-time history by design
  (`AutosavableFields` says so outright), and edit-time history of a story-time value is
  its own feature. See the open end.
- No change to retention, pruning or purging.
- No change to how autosave writes. This is about what is recorded, not when.
- No cross-entity or project-wide activity feed.

## Approach

- Widen the registry, or sit a second registry beside it, so a field can be tracked
  without being autosaved. `name` is a plain input, saved by the form, not by autosave —
  the two concerns are currently welded together and need separating.
- Aliases and tags are relations, not columns. Record a change as the resulting set, and
  describe it as the difference from the previous set.
- Summaries are generated from the recorded change, not typed by the writer. `RevisionSummary`
  and `ChangeExcerpt` already exist for the text fields; the same idea, applied to a name
  or a set.
- `RevisionHistory` already folds rows into save points. The four-row problem is that
  baselines and origins are shown as peers of the save. Fold them and label the result by
  what the writer did.

## Open ends

- Whether the writer can ask "when did this attribute value change" at all, given
  attribute values are story-time. She asked for it by name ("when did I change her
  hair"), and the current answer is that the question does not typecheck.
- Whether a rename should be revertible like a description, or only readable. Reverting a
  name that later chapters already use is a different kind of undo.
- Whether tags belong here. A tag is closer to a filing decision than a story fact, and
  its history may be noise.
