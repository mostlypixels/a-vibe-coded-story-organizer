---
title: Quick Appearance Switcher — Plan Overview
---

# Plan Overview

Manual. Never itself implemented or moved to `plan/implemented/`.

## Execution order

| # | Task | Purpose |
|---|------|---------|
| 01 | `01-shared-appearance-helpers.md` | Move the preview map, swatch colours and scale label out of Blade into support code. No visible change. |
| 02 | `02-dropdown-close-on-click.md` | `closeOnClick` prop on `x-dropdown`, default `true`. |
| 03 | `03-json-appearance-update.md` | `AppearanceController@update` answers 204 to a JSON request. Pin the one-field `PATCH`. |
| 04 | `04-switcher-panel-endpoint.md` | `admin.appearance.switcher` route, invokable controller, panel partial, `x-theme-swatch`. Depends on 01. |
| 05 | `05-switcher-javascript.md` | `appearance-switcher.js`: load, live preview, step, save queue, failure. Depends on 03, 04. |
| 06 | `06-navigation-docs-changelog.md` | Palette button in the top bar, hidden on the Appearance page. Docs, CHANGELOG, manual check. Depends on 02 to 05. |

The button comes last, so no user sees a panel that cannot save.

## Binding design decisions (do not re-litigate)

Resolved in the grill; recorded in `../resolution-log.md`.

1. **Panel fields:** theme swatches; per font (interface, manuscript): family `<select>`, size −/+,
   line spacing −/+. Nothing else.
2. **No locale, no accessible-font marks, no reset button.** The panel links to the full page.
3. **No button on `admin.appearance.edit`.** A switcher change there makes the form stale.
4. **Button at every width.** On phones it sits beside the hamburger.
5. **Font `<option>`s carry `style="font-family: …"`.** Best effort; no custom listbox.
6. **Routes:** new `GET admin.appearance.switcher`; writes reuse `PATCH admin.appearance.update`.
7. **Save queue, no timer.** One request in flight; a newer value replaces the queued one per field.
8. **Load on first open.** No preload on hover or focus. The dropdown is click-only, not `hover`.
9. **Error pages get no button.**

## Core invariants every task preserves

- **One-field `PATCH` changes one column.** `UpdateAppearanceRequest::validated()` holds only the
  fields sent. Never fill missing fields with defaults.
- **Every write goes to `$request->user()`.** No policy, no project walk, not the `access-admin`
  gate. Guest → login redirect.
- **CSS values come only from the server preview map.** `AppearancePreviewMap::build()` feeds the
  Appearance page and the panel. The JS rejects any slug not in the map, through
  `resolvePreview()` in `font-preview.js`.
- **Slugs resolve only through `ThemePreset::resolve()` and `FontChoice::resolve()`.**
- **The Appearance page keeps its current look and its form `PATCH` redirect.** Keep
  `AppearanceSettingsTest` green.
- **`x-dropdown` default behaviour does not change.** Every existing menu still closes on click.
