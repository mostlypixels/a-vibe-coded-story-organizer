# 07 — Documentation sweep

**Depends on:** 01–06.

## Scope

- `documentation/features/rich-text.md`: the decorative class contract, the `Rich` /
  `Structural` split and which seams pass which, and the three-surface styling list
  (`app.css`, the EPUB stylesheet, and nothing else) as a keep-in-step list.
- `documentation/interface/themes.md`: that five theme tokens now also colour author text,
  so changing their hue changes stored content's appearance. This is the non-obvious
  coupling this feature introduces — it is the reason the page exists.
- `documentation/features/revisions.md`: colour and alignment now appear in the compare
  screen, and how.
- One dated `CHANGELOG.md` section for the pull request.

**Not in scope:** `.specs/` content. The spec folder moves to `shipped/` through
`ship-plan`, not by hand.

## Key decisions

- Follow `.claude/rules/documentation.md`. Name the file, don't reproduce it.

## Tests

- `DocumentationLinksTest` stays green.
- No new test code. If a documented claim needs a guard, it belonged to the task that made
  the claim true.
