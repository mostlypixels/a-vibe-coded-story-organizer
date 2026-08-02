# 08 — Sweep: admin and codex pages

211 usages — the two heaviest page areas.

## Scope

`resources/views/admin/**` (122, of which `data/export-ebook.blade.php` alone is 30) and
`resources/views/codex/**` (89, of which `partials/fields.blade.php` is 14).

## Depends on

07.

## Key decisions already made

- Most of the weight is form controls repeated inline. Where a page hand-rolls markup that
  `x-input` / `x-select` / `x-card` already provide, **use the component** rather than renaming
  its classes in place — that is the cheapest moment to do it and it shrinks the diff.
  Note any such swap in `resolution-log.md`; it is a deviation from a pure rename.
- Status hues here are real statuses (import phases, export warnings): `red-*` → `danger`,
  `green-*`/`emerald-*` → `success`, `amber-*`/`yellow-*` → `warning`,
  `blue-*`/`indigo-*`/`bg-aqua-50` → `info`, each with its `-content` / `-surface` pair.
- Read each status usage as *is this status, or decoration?* Decoration becomes a surface or
  content token, not a status one.

## Consult

`expanded/architecture.md` → *Migration map* → *Surfaces and content* and *Status*.

## Tests

- `NoHueNamedColorsTest` allow-list loses `resources/views/admin/` and `resources/views/codex/`.
- `AdminConfigurationTest`, `AdminRevisionsPageTest`, `DataTransferTest`, `ImportTest`,
  `EpubExportTest`, `CodexEntryTest`, `CodexAttributeTest`, `CodexMediaTest` stay green.
- If a component swap changed rendered markup, the affected feature test's assertions move with
  it — update them in this task, not later.
