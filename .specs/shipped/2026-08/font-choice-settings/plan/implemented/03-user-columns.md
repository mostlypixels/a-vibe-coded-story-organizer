---
title: "Task 03 — User preference columns"
---

# Task 03 — User preference columns

## Scope

One migration adding five nullable string columns to `users`, and `User::$fillable`.

No writer and no reader yet — the controller is task 06, the style block task 04.

## Depends on

Nothing. Can run before or after 01/02.

## Key decisions already made

* Columns, in order, after `theme_slug`: `ui_font`, `manuscript_font`, `ui_scale`,
  `manuscript_scale`, `manuscript_leading`. All `->string()->nullable()`.
* **Five columns, not four** — `ui_scale` and `manuscript_scale` are separate settings
  (overview decision 3); `expanded/data-model.md` predates that and shows four.
* **Never write a default into a column.** `null` means "follow the config default", so
  changing `config('fonts.default_ui')` still reaches everyone who never picked. Same
  rule `theme_slug` and `timezone` already document in their own migrations — reference
  the reasoning, do not restate it at length.
* No index (read only via the already-loaded authenticated user), no enum column, no DB
  constraint: three engines are supported and the list lives in config, so validation is
  the Form Request's job and a stale value falls back rather than throwing.
* No backfill of existing users to `atkinson` (overview decision 5).
* `UserFactory` needs nothing — `null` is the realistic default state.

## Consult

* `expanded/data-model.md` (adjusting for the fifth column)
* The `theme_slug` migration — the comment to mirror

## Tests to add

None of its own beyond the suite staying green: there is no behavior yet. Task 06 covers
persistence, task 04 covers reads.
