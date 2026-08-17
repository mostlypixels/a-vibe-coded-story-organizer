# 15 — Group the Revisions browser by book

**Depends on:** 09.

## Scope

- `ProjectRevisionsBrowser::tree()` groups its manuscript entities (act, chapter, scene) under
  their book. Plotlines, events and codex entries stay ungrouped — they are project-scoped.
- The browser's sidebar and page render the book heading.

**Not in scope:** the per-field history and compare pages. They bind `{entity}` + `{id}`, carry
no project, and keep building their own trail tail.

## Key decisions

- The browser stays **project-scoped**. `revisions.project_id` does not move; a book heading is
  a rendering change, not a scope change.
- No history query may select `revisions.value` — `size_bytes`, `summary_html` and
  `change_count` exist so it does not have to, and a query-listener test guards it. Grouping
  must not break that.
- Use `displayName()` for the heading; a sole unnamed book still needs a label.

## Consult

`documentation/revisions.md`; `expanded/architecture.md` → *Other project-level services*.

## Tests

- `RevisionBrowserTest`: a two-book project groups its acts, chapters and scenes under the right
  book; plotlines and codex entries stay outside the grouping.
- The query-listener guard still passes.
