---
status: shelved
---

# Move Between Projects

A series planner with eight novels made eight projects, because "Project" reads as "book".
Nothing on the projects screen or the dashboard says a project holds many books with one
shared codex. By the time she found out, she had eight copies of every character and no way
to join them: nothing moves between projects, and export/import only ever carries a whole
project in and a whole project out.

The mistake is unrecoverable today, and it strands the work before any other series feature
can help her. See `.scratchpad/series-planner.md`.

## Goals

- Move a codex entry from one project to another, with its aliases, attribute values,
  media and lifespan events.
- Move a book, with its acts, chapters and scenes.
- Move an act into another book, across projects.
- Say what a project is where she decides: on the projects list and on the dashboard, one
  line that a project holds the whole series and shares one codex.
- Report what the move breaks before it happens, and let her cancel.

## Non-goals

- No merge of two entries into one. Duplicates stay duplicates; `duplicate-entities`
  (shipped) is the nearest thing and it copies, it does not join.
- No entry shared live by two projects. A move is a move.
- No change to export or import.
- No move of a scene on its own, or of a plotline, event or attribute definition. Those
  are the next round.

## Rough approach

- One `Services` action per movable thing, each in a transaction, each walking the same
  owning-project chain the policies already walk.
- The hard part is not the row. It is everything that points at a project: attribute
  definitions (`codex_attributes`), events referenced by attribute values and by
  `birth_and_death`, tags, plotlines, and the derived `scene_codex_entry` pivot.
- Open: an attribute value at "The Second Curse" is an event in the old project. Copy the
  event, match one by name in the target, or drop the value and tell her? Not decided.
- Open: an entry that scenes reference is being taken out from under them. Resync after
  the move probably answers it, but the writer should see the count first.
