---
title: "Task 06 — Request rename and controller"
---

# Task 06 — Request rename and controller

## Scope

Rename `UpdateThemeSettingRequest` to `UpdateAppearanceRequest`, add the five font rules,
and extend `AppearanceController::edit()` to pass what the picker will need.

The picker markup itself is task 07 — `edit()` passes the data, the view keeps rendering
only the theme card until then.

## Depends on

01 (the lists to validate against), 03 (the columns to write).

## Key decisions already made

* One request, one form, one PATCH, six fields: `theme_slug`, `ui_font`,
  `manuscript_font`, `ui_scale`, `manuscript_scale`, `manuscript_leading`.
* Every font rule is `['nullable', Rule::in(array_keys(config('fonts.<list>')))]`.
  `nullable` is load-bearing: it is how a user clears back to the config default.
* `authorize()` stays `$this->user() !== null`, **and its docblock stays** — the action
  writes only to the acting user, which is the documented exception to the ProjectPolicy
  walk. Do not "fix" it into a project walk.
* `update()` stays `$request->user()->update($request->validated())` — the only writer.
* `edit()` additionally passes the family list, the two scale lists, the leading list,
  and the resolved active values (via `FontChoice::resolve()`).
* No new route pair — `admin.appearance.*` already covers the page; it is "Appearance &
  accessibility" and fonts are the other half of it.
* Rename the existing request's test file alongside it.

## Consult

* `expanded/architecture.md` → *Controller & routes*
* `app/Http/Requests/UpdateThemeSettingRequest.php` — the docblock to carry over

## Tests to add

Extend `tests/Feature/AppearanceSettingsTest.php`:

* A valid PATCH persists all five columns; `null` clears one back to the default.
* A tampered `ui_font` / `ui_scale` value fails with `assertSessionHasErrors` and leaves
  the column unchanged.
* The rejected string never appears in a subsequent page render — the style-block
  guarantee proved, not asserted in a comment.
* User A's PATCH leaves user B's columns untouched (there is no non-owner case here;
  this is the assertion that replaces it).
* Guest is redirected from both routes — already covered; extend the PATCH payload.
