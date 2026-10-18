# Editor Interface — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

An exception log: a task that went to plan gets no entry, because the diff and the task
file already record what was built. Bullets under the headings below, root cause first.

## Feedback & decisions

- Spec typo: a codex pick loads into the Codex section, not Notes. Each section keeps its own cards.
- "Codex entries and attributes" means entry search, with attributes shown on the card as of the scene's event. No attribute search.
- Codex search matches aliases too, for every picker that uses `LinkTargetType::candidates()`.
- Scene switch is a full page load: the safest way to flush autosave and release the tab lock.
- The editor edits prose only. Other scene fields stay on `scenes.edit`.
- Recent scenes: the whole project, 15 rows.
- Below `lg`: panels are drawers, collapsed by default, and stored widths do not apply.
- The scene's linked notes load as collapsed cards. The user wants research for the current scene at hand.
- Card state is saved per user and per scene on the account (`editor_cards`), for searched and linked cards alike. The user chose the account over browser storage, and one model for all cards.
- Cards are read-only. The logo stays Dashboard.
- Full width and full height under the menus; each column scrolls alone, so a card lines up with the passage being written.
- Sticky editor toolbar; previous/next scene links; today's progress in the header.
- Scroll and cursor position stay in `localStorage`: screen size and browser differ between computers, so the account adds nothing.
- An entry not present at the scene's moment still shows a card, marked "Not present".
- Deferred: quick note creation from the editor.
- Side panels: 20 px side padding and a 4 px border next to the prose (user review). The header keeps 6 px while collapsed, so the button fits the 40 px rail.
- The navigation guard saves, then follows the link, on every page. It asks "Leave anyway?" only when a save fails. The user chose app-wide over Write-page-only.

## Deviations from the spec/plan

- `expanded/architecture.md` names the card body controller `EditorCardController`. That name went to the card state endpoint, so the card bodies come from `EditorCardContentController`.

- Full height needs a layout change: `x-app-layout` has a `full-height` prop. It drops the `py-12` and `--page-width` wrapper, makes the shell `h-dvh` flex column, and caps the open phone menu at `60dvh` with its own scroll. Flash alerts moved to `layouts/partials/flash.blade.php` so both modes show them.
- "Focus on load" became an opt-in `autofocus` prop on `x-wysiwyg` and `x-autosave-field` (Tiptap `autofocus: 'end'`). Task 06 cursor restore should replace the position, not add a second focus call.
- The `x-editor-panel` collapse button renders `aria-expanded` and `data-editor-collapse` but has no handler. Task 09 wires it. Below `lg` its `aria-expanded` still reads the stored flag, though the panel shows as a rail.
- `ProjectNavigation::$scenesActive` excludes `scenes.write`, so the Write page does not light Story.
- `word-count:reconcile` now bubbles (`autosave/field.js`), so the header "Today" listens on `window`. It also fires when another tab saves, which is correct: the scene count changed.
- Cursor restore is an opt-in `positionKey` prop on `x-wysiwyg` and `x-autosave-field`; the scroll box is the nearest `[data-position-scroll]`. A stored position turns off the `autofocus: 'end'` and focuses with `scrollIntoView: false`.

- Scene search results show the name only, with no book or chapter context: `LinkTargetType::candidates()` returns `{type, id, label}`. The recent list keeps the context line.
- Search results are real links (`<a role="option">`), and Enter clicks the highlighted one. This lets the navigation guard flush a pending save before the page load.
- Search results in the right panel are `role="option"` list items that call `pick()`, not links: a pick adds a card and does not leave the page.
- `x-icon-close-button` and `x-icon-view-link` take an optional `label` prop (default unchanged), for "Close {name}" and "Open {name} in a new tab".
- The right sections are plain `<section>`s with an `h3`. Task 09 owns the `<details>` and its `toggle` report to `editorLayout`.
- A card added by a search pick is a JS copy of an empty `x-editor-card` in a `<template>`. The JS fills the id, title, labels, view link and the fetched body. A change to the component markup must keep the `data-card-*` hooks.
- The panel collapse button is a plain `<button>`, not `x-icon-button`: its icon and label change with the state. Both icons render; CSS on `data-collapsed` shows one.
- The JS caps a width at 40% of the window as well as at `max_width`, so the handle value matches what the CSS `40vw` cap shows.
- Escape in a panel search with text clears the search only (`stopPropagation`). An empty search lets Escape close the drawer.
- Below `lg`, a section toggle changes the local state but sends nothing. The next `lg` change saves it with the full shape.

## Issues → resolutions

- The Docker Vite dev server polls every 60 s (`vite.config.js`), so new Tailwind arbitrary classes in a new Blade file are missing from dev CSS for a while. The grid looked broken (panels stacked) with a green suite. Check runtime layout against the build (move `public/hot` aside) or wait for the poll.
- `scripts/assets-state.sh` checks the host `database/database.sqlite`, but the Docker stack has its own database. Its two FAILs (live `public/hot`, host DB pending) are expected under Docker. The Docker DB was migrated with `docker exec ... php artisan migrate`.
- `css-build.test.js` failed after a fresh `npm run build`: the grid class `var(--editor-left)` has no CSS declaration (the value is an inline style). Task 05 passed only because the build was older. Fix: `var(--editor-left,2.5rem)` fallbacks.
- Restore runs in `$nextTick` after `ready`: the editor wrapper is `display: none` until then, so the scroll box has no height and `scrollTop` clamps to 0.
- `resolveEntry()` queried the project attributes once per entry, an N+1 for a page of codex cards. `CodexAsOfResolver::resolveEntries()` loads them once; `resolveEntry()` delegates to it.
- The query-count test first failed 34 vs 37: the first request of a test writes one-time rows (crawler settings, active project). The test sends a warm-up request before it counts.
- Alpine scope collision: the section `<details>` sits inside `editorCards`, so a direct `setSection()` call ran with the merged scope, and `this.save()` hit the card `save()` (PUT, 422). The green suite missed it: unit tests mount each component alone. Fix: the `<details>` dispatches `editor-section-toggle`, the layout root listens, and the layout save is `saveLayout()`.
- The plan assumed the navigation guard flushes autosave before a page load. It did not: with a save pending (the 2 s debounce), every scene switch opened "Unsaved changes — leave anyway?". Task agents missed it: they clicked links on a clean editor. Fix: `navigation-guard.js` waits for `flushAll()` through `settleAutosaves()`, then navigates; the dialog shows only when a field stays dirty.
- Browser checks typed test text into the user's real novel (Gardenia Girl, local `LongNovelSeeder`) in the dev database: scenes 1 and 143. Removed through the editor. Browser checks that type must use a scratch project.
- A hung task-agent shell (a heredoc write from 17:06) stayed alive after its agent returned. Stopped by PID; the file it wrote was already final.
- A one-pixel line of prose showed above the sticky toolbar (user report, high-DPI screen). `sticky top-0` rounds at fractional scroll offsets. Fix: `-top-px`.
