# Write outline tree — plan

## Order

| Task | Purpose |
| --- | --- |
| `01-editor-outline.md` | `EditorOutline` loads the book tree once; breadcrumb and New scene dialog read it |
| `02-recent-section.md` | `sections.recent` layout key; recent list in a folding section |
| `03-outline-ui.md` | The tree partial, search hides it, scroll to current row, docs |

## Binding decisions (from the grill)

- Current book only. No book switcher.
- One act in the book: no act heading. Two or more: every act heading shows.
- Acts are plain headings, never foldable.
- Chapter rows: `chapterLabel()` plus the chapter word total, muted (`x-word-count`).
- Scene rows: the name only. No scene number, no word count.
- Recent section: closed by default (`config('editor.layout.sections.recent') => true`). Its state is saved.
- Chapter fold state is not saved. Each load opens only the current chapter.
- No number search. No ARIA `tree` role: `<nav>`, lists, `<details>`, links.

## Invariants

- `StoryNumbering::fromActs()` gets every act, chapter and scene of the book. Never filter the tree.
- Each level sorts by `(position, id)`.
- Page-load query count does not grow with scene count.
- Scene switch stays a full page load through plain links (navigation guard).
- `EditorLayout::resolve()` stays the only reader of `users.editor_layout`.
- No new route or policy: `scenes.write` already authorizes `update` on the project.
