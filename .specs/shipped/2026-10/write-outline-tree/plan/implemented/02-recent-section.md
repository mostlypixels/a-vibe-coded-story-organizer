# 02 — Recent scenes section

## Scope

- Add `recent` to `EditorLayout::SECTIONS` and `config/editor.php` (`true`: collapsed by default).
- Wrap the recent list of `recent-scenes.blade.php` in a `<details>` styled like `card-section.blade.php`, sending `editor-section-toggle` with `section: 'recent'`.
- Search still hides the recent section while a query is typed (as now).
- Outline is task 03.

## Depends on

- None.

## Key decisions already made

- Style of the right-panel sections, not `x-collapsible-card` (too much padding for the panel).
- `layout.js` needs no change: it sends the full resolved `sections`.

## Docs

- `expanded/architecture.md` → Layout preference; `expanded/ui.md` → Panel order.

## Tests

- `EditorTest`: section closed with a null layout; open when stored `sections.recent` is `false`; current recent row still marked.
- `EditorLayoutPreferenceTest`: payload without `sections.recent` fails; existing valid payloads gain `recent`.
- `EditorLayout` resolve: missing `recent` key → default.
