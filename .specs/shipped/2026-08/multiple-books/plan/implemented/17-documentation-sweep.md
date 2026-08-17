# 17 — Documentation sweep

Bring `documentation/` in step. Last, so it describes what shipped rather than what was planned.

**Depends on:** 16.

## Scope

- `architecture.md`: the domain-model diagram, the authorization walk, *Continuous numbering*
  (now book-wide), *Routing*, *Story overview*, *Project picker*, *Breadcrumbs*, *Page title*,
  *Static file export*, *Static site import*, *EPUB export*. Add a **Books** section covering the
  name fallback, the last-book guard and `last_book_id`.
- `glossary.md`: add **Book**, **Book name fallback**. Amend **Project**, **Position**,
  **Number**, **Story overview**.
- `export-format.md`: rewritten for version 4 — the `data/books/` branch, `book.json`, the
  `books/` reading layer, the version contract.
- `epub-export.md`: one export per book, the appendix filter, `include_book_cover`.
- `word-count.md`: book totals alongside act, chapter and project.
- `CLAUDE.md`: the authorization example gains its level.
- `.specs/expanded/…/multiple-books/standing-issues.md` if any accepted cost survived — create it
  only if there is one.

**Not in scope:** `codex.md`, untouched because the timeline stayed project-scoped.

## Key decisions

- Entry point short, deep dive linked. A feature gets a compact section in `architecture.md`
  linking the full reference; the deep dive holds detail, not padding.
- Explain *why*, not *what*. Use GFM alerts for the pitfalls: the cascade/cover-purge trap, the
  `displayName()` rule, the reconciliation rule on import.

## Consult

`.claude/rules/documentation.md`; every `expanded/*.md` in this feature.

## Tests

- `DocumentationLinksTest` passes — the `export-format.md` rewrite must not break a link.
- `bash scripts/verify.sh` green overall.
