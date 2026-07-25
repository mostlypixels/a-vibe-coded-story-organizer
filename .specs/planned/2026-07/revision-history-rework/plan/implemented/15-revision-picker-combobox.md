# Task 15 — `x-revision-picker`: the APG combobox

## Scope

`resources/views/components/revision-picker.blade.php` + `resources/js/revision-picker.js`
(Alpine, registered like the existing components in `resources/js/app.js`), progressively
enhancing task 14's two `<select>`s.

Built to the **W3C APG select-only combobox pattern** — a native `<select>` cannot hold
the filter panel, and an unlabelled `<div>` dropdown tells a screen reader nothing:

| Concern | Contract |
|---|---|
| Roles | `role="combobox"` on the trigger, `role="listbox"` on the panel, `role="option"` per row |
| State | `aria-expanded`, `aria-selected`, `aria-controls`, `aria-activedescendant` maintained on every interaction |
| Keyboard | ↓/↑ move the active option (skipping disabled ones), Enter selects, Escape closes and returns focus to the trigger, Home/End jump, typing filters |
| Option label | `#<n> · <date> · <label> · <origin>` + a **Current** marker |
| Filters in the panel | *Manual saves only* toggle + a from/to date range, **independent per side, deliberately not synced** — so a bad save can be found by comparing a manual save against the autosaves around it |
| Constraint | The right picker disables every option not strictly newer than the left selection |
| Selection | Sets `from`/`to` in the URL and navigates — no client-side diffing |
| No-JS | Task 14's `<select>` + submit button remains the fallback; the enhancement replaces it only once Alpine is running |

Filtering inside the panel is client-side over the already-rendered option list (payload
size is not the constraint; human scanability is).

## Depends on

Task 14.

## Key decisions already made

* Progressive enhancement, not replacement — the accessible baseline must keep working.
* Filters are per side and deliberately **not** synced (the whole point is comparing one
  manual save against nearby autosaves).
* The option list is server-rendered; a project with thousands of save points is handled
  by the in-panel filters, not by paging the dropdown.
* All Alpine state logic in the JS module (unit-testable), not inline expressions in the
  Blade — unlike the sidebar filter, this one is too big to live in attributes.

## Consult

* `expanded/ui.md` — *3. The save-point combobox*, and the accessibility checklist.
* `notes/revision-ui-lexicon.md` §3–4 — the pattern vocabulary and the APG links.
* `resources/js/wysiwyg.js` + `wysiwyg.test.js` — the existing "JS module + co-located
  vitest" shape to follow.

## Tests

`resources/js/revision-picker.test.js` (vitest):

* arrow keys move the active option and update `aria-activedescendant`; disabled options
  are skipped;
* Enter selects and triggers navigation with the right query string;
* Escape closes the panel and returns focus to the trigger;
* Home/End jump to first/last enabled option;
* `aria-expanded` tracks the panel state;
* typing filters the list; the manual-only toggle and the date range narrow it;
* the two sides' filters do not affect each other.

Plus a Blade-render assertion in `RevisionCompareTest` that the enhanced markup carries the
required ARIA attributes server-side (so the no-JS DOM is already correct).
