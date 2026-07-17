# 04 — Publication-setting config form & persistence

## Scope
The Export-ebook configuration form and its write path. Persists the setting; the exporter still
does **not** consume it yet (that's tasks 08+), so tests here assert persistence, not epub output.

- `PublicationSettingController@update(UpdatePublicationSettingRequest, Project $project)`:
  `authorize('update', $project)`, `firstOrNew` on `project_id`, fill validated input, save,
  redirect back with a status flash. Route
  `PATCH /data/export/ebook/{project}/settings` (`data.publication-settings.update`).
- `UpdatePublicationSettingRequest`: `authorize()` = `can('update', $project)` (mirror the
  controller). Rules: every `include_*` `['boolean']`; the three enums `[Rule::enum(...)]`;
  `appendix_entry_types` `['array']` + `.*` `[Rule::enum(CodexEntryType::class)]`; `section_order`
  a custom rule — **exactly** the known component keys, no dupes/unknowns, `title` first,
  `toc`+`body` present. Booleans normalize the usual checkbox way.
- Export-ebook view (`admin/data/export-ebook.blade.php`): project `<select>` that **reloads**
  (`GET …/export/ebook?project={id}`) to load that project's saved settings; controller's
  `exportEbook()` loads `publicationSettingOrDefault()` for the selected/first project. Grouped
  fieldsets (see `ui.md`): content toggles, metadata toggles (show the stored value + a link to
  project edit), formatting selects (each `<option>` = enum `label()`, with a live example for
  chapter-title format), appendix (disclosure toggle + type checkboxes + include-images),
  section-order list reordered with **move-up/down** buttons (reuse the acts/chapters/scenes
  idiom — submit an ordered array, or per-item move actions mirroring `ActController::moveUp`),
  and **help text pointing to the project edit page** for the actual dedication/preface text.
  Two separate buttons: **Save configuration** (this PATCH) and **Download EPUB** (existing
  `data.export.epub` POST) — Download exports the **saved** settings; label its section so.

Does **not**: serialize the setting into the archive (task 05), change epub output (tasks 08+),
edit the Markdown text (task 02 — project edit page).

## Depends on
01 (model/enums), 03 (pages exist).

## Key decisions already made
Overview #4 (sortable order + independent toggles), reload-on-select + two buttons, Download uses
saved settings, appendix types as checkboxes. Authorization is `update` on the project, mirrored.

## Docs to consult
`../expanded/architecture.md` §A/§C/§D, `../expanded/ui.md` §"Export-ebook config form".

## Tests to add
`PublicationSettingTest` (feature): owner save creates the row; second save updates the same row
(unique `project_id`, no dup); non-owner PATCH ⇒ 403, guest ⇒ redirect; invalid enum ⇒
`assertSessionHasErrors('divider_type')`; bad `section_order` (dupe/unknown/`title`-not-first) ⇒
errors; unknown `appendix_entry_types.*` ⇒ error; saved values reload into the form.
