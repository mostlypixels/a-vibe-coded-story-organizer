# Task 08 — Documentation & regression sweep

## Scope

The wrap-up task: documentation updates and cross-cutting regression coverage that only
make sense once every construct-specific task (01–07) has landed.

- **`documentation/rich-text.md`**:
  - Update the allow-list table to include the new tags/attributes from task 01.
  - Rewrite the "Underline and Strike are **disabled** in this mode" sentence under "Two
    modes" — both are now enabled in Markdown mode (task 05).
  - Fix the stale claim in `RichTextFields`' docblock (`app/Support/RichTextFields.php`)
    and this doc: `Scene.contents` is *"never routed through the sanitizer **or the
    editor**"* — the sanitizer half stays true, the editor half is stale
    (`scenes/edit.blade.php` already renders it with `<x-wysiwyg … markdown>`). Fix both
    the docblock comment and this doc's prose.
  - Add a short section (or extend an existing one) documenting the three fallback-list
    checks from task 07 and pointing at `autosave-with-revisions` as their consumer, so
    a future reader of this doc understands why the checks exist without re-reading the
    spec.
- **`CLAUDE.md`**: add `npm run test` (vitest) to the Commands section, per
  `autosave-with-revisions` §9.12's note that whoever adds vitest first must do this.
- **Import-path regression test**: confirm `app/Services/Import/ContentSanitizer`'s
  `assertMarkdownAllowed()`/`assertHtmlAllowed()` now accept a `.zip` import containing a
  Markdown table/image/task list, where they may previously have rejected the rendered
  HTML as "disallowed content" before task 01's allow-list widening. This is a real
  behavior change (import acceptance, not just editor capability) — find
  `ContentSanitizer`'s existing test file and add a named test for it, not an implicit
  side effect.
- **Final `WysiwygFormTest` pass**: run the full existing suite to confirm the
  progressive-enhancement `<textarea>` contract survived every task's toolbar/Blade
  changes untouched — no textarea-rendering assertions should need updating.
- **Full-suite sweep**: run `composer test` and `npm run test` together, confirming no
  task-boundary regression (e.g. task 05's toolbar diff breaking task 04's table
  buttons).

## Depends on

Tasks 01–07 (all of them) — this is the final task in the plan.

## Key decisions already made

- Nothing new decided here; this task only executes documentation/regression work
  implied by prior tasks' own "docs to consult"/test notes.

## Docs to consult

- `../expanded/ui.md`'s "`documentation/rich-text.md` updates" section for the exact
  required edits.
- `../expanded/testing.md`'s "Import path regression" and "`WysiwygFormTest`" bullets.
- `../expanded/spec.md`'s "Loose end spotted while writing this" section for the stale
  docblock claim.

## Tests

- No new unit tests beyond the import-path regression test called out above — this task
  is primarily documentation plus a full-suite confirmation run. If the full-suite
  sweep surfaces a regression, fix it here and record it in `../resolution-log.md` under
  "Issues → resolutions" rather than silently patching it.
