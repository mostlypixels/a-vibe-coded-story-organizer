---
title: "Task 01 — Font config and FontChoice"
---

# Task 01 — Font config and FontChoice

## Scope

`config/fonts.php` — the whole vocabulary — and `App\Support\FontChoice`, the single
entry point that turns five stored slugs into resolved CSS values.

Renders nothing and reads no user row yet: the style block is task 04, the columns are
task 03. No `@font-face`, no woff2 (task 02).

## Depends on

Nothing — first task.

## Key decisions already made

* Config, not an enum (overview decision 1). Model the file on `config/themes.php`,
  including its commenting style: say *why* a value is authored here, don't restate it.
* Lists: `families`, `ui_scales`, `manuscript_scales`, `leading`, plus a
  `default_*` key per list.
* Per family: `name`, `stack` (full authored CSS font-family list, already quoted),
  `bundled` (bool), `note` (the reason it is on the list — the picker shows it).
* Nine families: `inter`, `atkinson`, `lexend`, `literata`, `source-serif-4`
  (`bundled => true`); `arial`, `verdana`, `georgia`, `system` (`bundled => false`).
* `default_ui` and `default_manuscript` are both `inter`.
* `ui_scales` values are percentages for `:root{font-size}`. `manuscript_scales` values
  are percentages applied on `.prose`, **relative** to that root — so `normal` is `100%`
  and the labels read *same / larger / largest* (overview decision 4).
* `FontChoice::resolve(?string $ui, ?string $manuscript, ?string $uiScale, ?string
  $manuscriptScale, ?string $leading): self` — falls back per field to the config
  default when the slug is `null` **or** no longer configured. Mirror
  `ThemePreset::resolve()`; read it before writing this.

## Consult

* `expanded/architecture.md` → *Config, not database*
* `config/themes.php` and `app/Support/ThemePreset.php` — the shape to mirror

## Tests to add

`tests/Unit/FontConfigTest.php`:

* Every family declares `name`, `stack`, `bundled`, `note`.
* Every `default_*` key is a key of its own list.
* Exactly the five expected families are `bundled => true` (the `@font-face` and
  file-existence halves of this test arrive with task 02).

`tests/Unit/Support/FontChoiceTest.php`:

* A `null` field resolves to the config default.
* A slug removed from config resolves to the default rather than throwing.
* A valid slug resolves to that entry's authored value, per field.
