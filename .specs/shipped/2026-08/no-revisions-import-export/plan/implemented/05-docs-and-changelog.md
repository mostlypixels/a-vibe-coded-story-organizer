# 05 — Documentation and changelog

## Scope

| File | Change |
|---|---|
| `documentation/export-format.md` | `data/manifest.json`'s table and example drop nothing (the key was never documented) — but add a short note that revision history is deliberately **not** exported, and why: archive size, and imported rows being unprunable by design. Bump the documented `version` to **3** and rewrite the version-contract callout: v2's "one bump per feature" note is now history, and the contract's live statement is that **only version 3 is supported**. |
| `documentation/revisions.md` | *Prune vs purge* table and the categories paragraph lose `imported`; the origin list under *Writing rows* loses `import`; the `ProjectGraphImporter` replay sentence under *Summaries* goes. |
| `documentation/architecture.md` | *Static site import* — one line: revisions are not part of the archive contract, and pre-v3 archives are rejected. |
| `CHANGELOG.md` | One dated section, per `.claude/rules/changelog.md`. |

## Depends on

01–04.

## Key decisions

- The revisions sidecar was **never documented** in `export-format.md` — there is nothing to delete
  there, only a "not exported" note to add. Don't go hunting for a section that doesn't exist.
- The changelog entry must call out the **breaking** part plainly: archives exported before this
  change can no longer be imported.
- `.claude/rules/documentation.md` verbosity rules apply — lists, no padding, explain *why* the
  archive stops carrying history rather than restating what was removed.

## Tests

None of its own. `tests/Unit/DocumentationLinksTest.php` already guards cross-doc links; run the
suite to confirm no link broke.

## Consult

`expanded/architecture.md` → *Documentation* · `.claude/rules/documentation.md` ·
`.claude/rules/changelog.md`.
