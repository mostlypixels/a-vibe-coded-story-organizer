# 06 — Documentation

**Depends on:** 05.

## Scope

* A compact *Duplicating entities* section in `documentation/architecture.md`: which two entities
  have the action, the owns-vs-points-at split, the files-before-transaction inversion and why,
  and the derived-cache rule. Entry-point short — no deep-dive page; the feature is not big enough
  to earn one.
* `documentation/codex.md`: note that a duplicated entry inherits its aliases, so the copy matches
  the same scenes until the writer edits them.
* Check `documentation/glossary.md` for a "duplicate" entry if the term needs pinning against
  "import" and "revision".

Not in scope: `CHANGELOG.md` — `ship-pr` owns that.

## Key decisions

* Document the **inversion of the disk/transaction convention** explicitly. It is the one place
  this feature contradicts a rule stated elsewhere in the docs, and an undocumented contradiction
  reads as a bug to the next reader.
* Do not restate the copied-column tables from `expanded/data-model.md`; the spec survives into
  `.specs/shipped/` and is citable.

## Consult

`.claude/rules/documentation.md`; the shipped code from tasks 02–05 (document what exists, not
what the spec proposed).

## Tests

None. Prose only.
