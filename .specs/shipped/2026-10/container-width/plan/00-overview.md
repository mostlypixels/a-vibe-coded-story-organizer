# Container Width — plan

## Order

1. `01-storage-and-render.md` — column, config, `PageWidth`, validation, `--page-width` in the layout.
2. `02-appearance-page.md` — "Page width" track on the Appearance page with live preview.
3. `03-switcher-and-docs.md` — − / + row in the quick switcher; interface docs.

## Binding decisions (grill, 2026-10-11)

- Steps 50, 60, 70, 80, 90, 100 %. Default **70**. Lowest step stays 50.
- Steps apply from `md` (768 px); full width below.
- One control. The manuscript follows the page; no separate manuscript width.
- Column `users.page_width`; UI label "Page width"; switcher row "Width", buttons "Narrower page" / "Wider page".
- Labels go through `ScaleLabel::format($value, 'width')`: 100 % → "Full width". No `PageWidth::label()`.

## Invariants

- Step values reach CSS only through `PageWidth::css()` and the preview map — never from input.
- The step is an **integer** everywhere: column cast, `selected` in `x-setting-track` (`===`), switcher `active`. See `expanded/architecture.md` → Pitfall.
- No new authorization: the field is the acting user's own column, saved through `AppearanceController@update`.
- Navigation, breadcrumbs, guest, error and share pages are untouched.
