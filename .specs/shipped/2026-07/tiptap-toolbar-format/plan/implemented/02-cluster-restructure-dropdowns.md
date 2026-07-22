# Task 02 — Regroup toolbar into 5 clusters, wrap Headings/Table structure in dropdowns

## Depends on

- **01** (`01-toolbar-button-component.md`) must be in `plan/implemented/` first — this task
  populates dropdown contents (and the remaining inline clusters) with
  `<x-wysiwyg.toolbar-button>` instances built by task 01.

## Scope

Regroup `resources/views/components/wysiwyg.blade.php`'s toolbar `<div role="toolbar">` into 5
named clusters, in this exact order, each still separated by the existing divider tick
(`<span class="mx-1 h-5 w-px bg-gray-300"></span>`), including before/after each dropdown trigger:

1. **Headings** — collapsed into `<x-dropdown>`. Trigger button shows the active heading level's
   label (`H1`/`H2`/`H3`/`H4`) when `isOn('heading', { level })` is true for one of them, else a
   plain `H`; `title="{{ __('Heading') }}"` / matching `aria-label`. Dropdown content: the 4
   `x-wysiwyg.toolbar-button` instances from the `$headings` array (built in task 01).
2. **Text format** — inline, unchanged row of 4 buttons (Bold/Italic/Underline/Strike) built from
   the `$textFormat` array.
3. **Lists & blocks** — inline, unchanged row of 6 buttons (bullet list, ordered list, task list,
   blockquote, inline code, code block) built from the `$listsAndBlocks` array.
4. **Insert** — inline, hand-written buttons: Link (`setLink()`), Horizontal rule
   (`setHorizontalRule` via `cmd()`), Table (`insertTable` via `cmd()`), Image (`setImage()`),
   Callout (`toggleCallout()`). Table and Image move up from their current position (after the
   table-structure buttons) to sit adjacent to Link/HR/Callout in this cluster — markup for each
   of these 5 buttons is otherwise unchanged from today.
5. **Table structure** — collapsed into `<x-dropdown>`. Trigger button uses a distinct glyph from
   the Insert cluster's Table button (e.g. `&#9638;&#9998;` or similar — pick something visually
   distinguishable from `&#9638;`), `title="{{ __('Table structure') }}"` / matching `aria-label`
   (must read differently from cluster 4's `title="{{ __('Table') }}"` so the two aren't
   confused). Dropdown content: the `x-wysiwyg.toolbar-button` row from the `$tableStructure` array
   (4 buttons in Markdown mode, 6 in HTML mode — merge/split still appended only when
   `! $markdown`, same gate as today).

For both dropdowns, use `<x-dropdown align="left" width="auto" contentClasses="p-1 bg-white flex items-center gap-0.5">`
so the popover renders as a horizontal button row hugging its content instead of the component's
default vertical `w-48` menu sizing.

## Key decisions already made (binding — see `00-overview.md`)

- Reuse `<x-dropdown>` (`resources/views/components/dropdown.blade.php`) as-is — no new dropdown
  component.
- Cluster order is fixed: Headings → Text format → Lists & blocks → Insert → Table structure.
- No contextual enable/disable of table-only buttons outside a table, and no auto-opening the
  Table structure dropdown when the cursor enters a table — both explicitly out of scope.
- The toolbar's outer container keeps `role="toolbar"` and `aria-label="{{ __('Formatting') }}"`.
- Merge/split cell buttons render only when `! $markdown` — same gate, now inside the Table
  structure dropdown's content instead of inline.
- Dropdown open/close (`<x-dropdown>`'s own `x-data="{ open: false }"`) is independent Alpine
  state from the editor's `x-data="wysiwyg(...)"` — no changes needed in `resources/js/wysiwyg.js`;
  `cmd()`/`isOn()` remain reachable from inside the dropdown's `$content` slot via Alpine scope
  nesting.

## Docs to consult

- `../expanded/ui.md` — full reuse decision, cluster list, and the exact `contentClasses`/`width`
  values for `<x-dropdown>`.
- `../expanded/overview.md` — acceptance criteria (button/command parity, gate preservation,
  `role="toolbar"` preservation).
- `../expanded/open-questions.md` — Q2 (recommended: no auto-open), Q3 (recommended: plain `H`
  trigger label when no heading level is active — implementer may adjust the glyph for visual
  clarity as long as active-state highlighting still works).

## Tests to add

Add one Feature test (new method in `tests/Feature/WysiwygFormTest.php`, matching its existing
style: `RefreshDatabase`, `actingAs($user)`, `route()` helper, fixture via the existing
`fixture()` private helper) that renders an HTML-mode wysiwyg form (e.g.
`route('projects.edit', $project)`) and asserts the toolbar still exposes its key commands after
the regroup, e.g.:

```php
public function test_toolbar_still_exposes_key_formatting_commands_after_regrouping(): void
{
    [$user, $project] = $this->fixture();

    $this->actingAs($user)
        ->get(route('projects.edit', $project))
        ->assertOk()
        ->assertSee('aria-label="Bold"', false)
        ->assertSee('aria-label="Heading"', false)
        ->assertSee('aria-label="Table structure"', false)
        ->assertSee('aria-label="Formatting"', false); // outer toolbar container, unchanged
}
```

(Adjust exact `aria-label` strings to whatever wording task 01/02 land on for the Heading and
Table-structure dropdown triggers — keep them matched to the `title`/`aria-label` actually used
in the markup.)

Also required before marking this task done:
- `resources/js/wysiwyg.test.js` and the existing `WysiwygFormTest` methods continue to pass
  unmodified.
- `npm run test`, `composer test`, and `composer lint` all pass.
