# 11 — Goals and snapshots travel in the export

## Scope

- The export archive carries the project's two goals **and** its `word_count_snapshots` rows.
- The importer restores both during `ImportPhase::Project` — neither depends on the story
  tree, and the project row exists by then.
- Snapshot rows are written in bulk with `DB::table()`, per the standing rule.

## Depends on

02 (the table), 05 (the goals).

## Key decisions

- **An export is a backup.** A backup that silently drops the writing history is not a backup
  of the project. This reverses the expansion's first instinct, and it is also what removes
  the need for any import-time baseline row.
- **An archive with no snapshots section imports as "none", not as an error.** Every export
  written before this feature is in that shape.
- Restoring an old archive leaves a gap in the history and a large jump on the day of the
  restore. That is honest — the words did appear, from outside the app — and needs no
  special handling.
- Importing the same archive twice yields two projects with identical history. Fine.
- Do **not** call the recorder at the end of an import. The restored rows are the history;
  a recorder call would add a same-day row on top and is the mechanism this decision replaced.

## Consult

`expanded/data-model.md` → *Export / import* · `expanded/open-questions.md` → *Closed by the grill*

## Tests

- Round trip: export a project with goals and snapshots, import it, assert both survive and
  the restored project's series matches the original's.
- Import an archive **without** a snapshots section → succeeds, no rows, no error.
- Ownership: the imported project belongs to the importing user, and its snapshots with it.
