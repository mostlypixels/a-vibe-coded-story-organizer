# Task 04 — Table & image UI (toolbar, slash menu, resize, merge/split)

## Scope

Give writers a way to actually create tables, images, and task lists — the UI surface
on top of task 03's capability wiring:

- **Slash menu** (`buildSlashItems()` in `resources/js/wysiwyg.js`): add `Table`,
  `Image`, and `Task list` entries. Table inserts a default grid (e.g. 3×3 with header
  row via `insertTable({ rows: 3, cols: 3, withHeaderRow: true })`); Image needs a
  prompt (mirror `setLink()`'s `window.prompt` pattern — add a sibling `setImage()`
  Alpine method); Task list is a plain `toggleTaskList()` toggle.
- **Toolbar** (`resources/views/components/wysiwyg.blade.php`): add a Task-list entry to
  the existing `$toggles` array (same shape as Bulleted/Numbered list). Table and Image
  get their own buttons outside the `$toggles` loop (same tier as the existing Link and
  Horizontal-rule buttons, since both take arguments/prompts rather than being a plain
  toggle).
- **Image resize — HTML-mode fields only.** `Image.configure({ resize: true })` when
  `! isMarkdown`, left off (`resize` unset/false, task 03's baseline) when `isMarkdown`.
  No resize toolbar button needed — resize handles are the extension's own in-editor
  UI once enabled.
- **Table merge/split — HTML-mode fields only.** Add `mergeCells`/`splitCell` toolbar
  buttons, rendered/enabled only for `! $markdown` fields — there is no existing
  merge/split UI to modify, this is new. For Markdown-mode fields, do not render this
  affordance at all (the `isMarkdown` conditional pattern, same as the existing
  strike/underline suppression before task 05 removes it there).
- Icons: follow the existing glyph-as-label convention (`B`, `I`, `&bull;`, `&mdash;`,
  etc. — no icon font or SVG asset). Exact glyph choice is a free implementation
  decision; the constraint is "no new asset dependency."
- Widen `RichTextFields::ALLOWED_ATTRIBUTES['img']` to include `width`/`height` if task
  01 didn't already include them (confirm — task 01 scoped this in, but re-verify here
  since resize is what actually emits them).

## Depends on

Task 03 (extension wiring must exist before UI can invoke it) and task 01 (allow-list
must already accept `table`/`img`/task-list output, including `width`/`height` on
`img`, before this UI can be exercised end-to-end through a real save).

## Key decisions already made

- **HTML-mode only** for both resize and merge/split — this was explicitly grilled and
  confirmed, not a default. Markdown-mode fields get neither, ever, in v1.
- Resize and merge/split are **folded into this task**, not a separate follow-up —
  confirmed in the grill specifically to avoid a thin "resize/merge" task with no
  independent narrative.
- The `isMarkdown` conditional this task introduces for merge/split is **new** code (no
  existing merge/split UI to gate) — don't confuse it with the `isMarkdown` conditional
  task 05 is *removing* for underline/strike; they're opposite operations on similarly-
  shaped conditionals in the same file.

## Docs to consult

- `../expanded/ui.md` for the full slash-menu code sketch, toolbar button placement,
  and the CSS needs (table borders, task-list checkbox styling — the `.tiptap` block in
  `resources/css/app.css`).
- `../expanded/architecture.md` §2 ("Prevent, don't just warn, for Markdown-format
  fields") for the prevention rationale this task implements.

## Tests

- Extend `resources/js/wysiwyg.test.js` (task 03's file): resize and merge/split
  commands are available (extension configured) when format is `html`, and the
  slash-menu/toolbar item lists for `markdown` format never include a merge/split or
  resize-triggering entry.
- `tests/Feature/WysiwygFormTest.php`: run the existing suite unmodified to confirm the
  progressive-enhancement textarea contract still holds with the new toolbar buttons
  present — no new assertions expected, per `../expanded/testing.md`.
- Manual verification via the `run-imagoldfish` skill: insert a table and an image
  through the slash menu in both a rich-HTML field and a Scene-contents field, confirm
  save round-trips correctly, and confirm merge/resize controls are visibly absent on
  the Scene-contents (Markdown) field.
