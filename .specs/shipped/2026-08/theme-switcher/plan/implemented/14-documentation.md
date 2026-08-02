# 14 — Documentation

## Scope

- `documentation/architecture.md` — a compact **Theming** section, linking the deep dive. Update
  the existing *CSS build pipeline (Tailwind 4)* section: it currently cites
  `--color-ocean-500` as the example token and says the border shim is removed by "spec 2" —
  both are now history.
- `documentation/theming.md` — the deep dive.
- `documentation/ui-components.md` — `x-badge`'s variant list changed (`indigo`/`gray` are gone);
  any documented class names that moved.
- `CHANGELOG.md` — a dated section for the PR.

## Depends on

13.

## Key decisions already made

- Entry point short, deep dive linked — the `revisions.md` pattern.
- `documentation/theming.md` covers what the code cannot say:
  - the paired-token rule, and why an unreadable combination must be unrepresentable;
  - the **flat token vocabulary** — `bg-primary`, never `bg-primary-600`. Ramps are an authoring
    tool, not public vocabulary. This is the rule most likely to be broken by habit;
  - plain `@theme` vs `@theme inline`, and never wrapping the style block in `@layer` — both
    fail silently, which is what makes them worth a `> [!WARNING]`;
  - contrast floors reject / per-preset ceilings warn, and **why a ceiling exists at all**
    (halation, and that different conditions pull the ideal in different directions);
  - how to add a token, and how to add a preset with `theme:ramp`;
  - why there is no `dark:` variant.
- **CHANGELOG entries: one line each, ~20 words, what changed not how.** A normal PR is 1–5
  entries. This one is genuinely user-visible on several axes, but "renamed 900 classes" is not
  an entry — "the app can be switched between three colour themes" is.
- Do not document the token table exhaustively in prose. Name `ThemeTokens` and move on; the
  reader can open it.

## Consult

`CLAUDE.md` → *Documentation* and *Changelog*; `documentation/revisions.md` as the shape to copy.

## Tests

- `tests/Unit/SpecsStatusConsistencyTest` stays green.
- No new tests. If this task wants one, it belongs in an earlier task.
