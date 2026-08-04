# 09 — Documentation

## Scope

- `documentation/glossary.md` — *position* (per-parent sort key, gappy, what move up/down
  writes) vs *number* (project-wide display rank, derived, never stored).
- `documentation/architecture.md` — a compact "Continuous numbering" section: where the map
  is built, and the three do-not-touch sites (static-site hrefs, archive JSON, reorder
  logic). Link out rather than re-explaining.
- `documentation/epub-export.md` — chapter numbers are continuous; the EPUB now exports
  placeholder chapters as heading-only pages, and refuses only when the project has no
  scenes at all.
- `documentation/export-format.md` — no change. The `data/` layer still carries `position`,
  so it stays true as written.
- `CHANGELOG.md` — a dated section for this PR.

## Depends on

01–08.

## Key decisions

- Entry-point-short, deep-dive-linked. No doc restates the value object's code.
- Changelog entries are user-visible changes, one line each, no class names — likely four:
  continuous numbering, the `#`-sort fix, the scenes list column, and the EPUB now exporting
  unwritten chapters.
