---
title: "Task 07 — Picker UI"
---

# Task 07 — Picker UI

## Scope

The font and sizing cards in `resources/views/admin/appearance/edit.blade.php`, fully
working with **no JavaScript** — submit, save, reload, see the change.

Live preview is task 08 and must not be needed for this task to be verifiable.

## Depends on

06 (`edit()` supplies the lists and active values).

## Key decisions already made

* New `x-card`s below the existing theme one, **in the same form** — one `Save`, one
  round-trip.

  | Card | Controls |
  |---|---|
  | Interface font | radio list, one per family |
  | Manuscript font | radio list, same families, + a prose sample |
  | Text size | two radio lists: interface scale, manuscript scale |
  | Line spacing | radio list, 3 steps |

* **Native radios, no Alpine** — arrow-key navigation for free, and it matches the theme
  picker. A slider was rejected: it implies a continuum the config does not have.
* Each family's label carries its config `note`, not just its name — the list only helps
  if the reader knows why Lexend is there.
* Each family label is rendered **in that family** (`style="font-family: …"` from the
  config stack) so the list previews itself. The stack is authored config, never input.
* The manuscript card shows one sample paragraph in the selected family, size and
  leading. Real text — do **not** `aria-hidden` it; someone changing line spacing for a
  sighted partner is a real case.
* The manuscript scale fieldset's options read *same / larger / largest*: it is relative
  to the interface scale (overview decision 4). Say so in the legend or help text.
* Fieldset + `<legend>` per group, `sr-only` legend only where the card header already
  names it, as the theme fieldset does. `x-input-error` under each fieldset.
* Reuse existing components before adding any.

## Consult

* `expanded/ui.md` → *The picker*, *Keyboard & a11y*
* The theme fieldset already in `edit.blade.php` — the pattern to follow

## Tests to add

Extend `tests/Feature/AppearanceSettingsTest.php`:

* The picker lists every configured family, with the active one `checked` for each of the
  five fields.
* A user who never picked sees the **config defaults** marked active, for all five.

Verify by hand with the `run-imagoldfish` skill: tab through every fieldset, arrow
between radios, save. A keyboard-inaccessible display-settings page is a contradiction,
and no feature test catches it.
