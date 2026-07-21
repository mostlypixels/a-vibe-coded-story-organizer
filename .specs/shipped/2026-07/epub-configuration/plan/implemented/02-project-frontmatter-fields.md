# 02 — Project front/back-matter Markdown fields

## Scope
The four Markdown content fields, on the project, edited on the project edit page, surviving a
`.zip` round-trip. **This task owns the single manifest-version bump** that every later
archive-touching task rides.

- Migration `add_frontmatter_to_projects_table`: `dedication`, `acknowledgements`, `preface`,
  `postface` — all `text` nullable, after `cover_image`. Add all four to `Project::$fillable`.
  Keep them **raw Markdown** — do **not** add them to `SanitizesRichHtml`.
- Project edit UI (`resources/views/projects/edit.blade.php`): a grouped section
  **"Book front & back matter (Markdown)"** with four plain `<textarea>`s (NOT `x-wysiwyg`) +
  `<x-input-error>` each, using `old(...)`. Clarify these are Markdown (contrast with the adjacent
  WYSIWYG description).
- `UpdateProjectRequest` (+ `StoreProjectRequest` if it shares rules): each field
  `['nullable','string', new ValidMarkdown]` (+ a sensible `max`). `ProjectController@update`
  already mass-assigns fillable — confirm the four save.
- **Archive round-trip:** add the four fields to the project descriptor in `StaticSiteExporter`
  and read them back in `ProjectImporter`; extend `App\Support\ImportRules`/the manifest
  allow-list; **bump the manifest `version`** and keep the importer tolerant of pre-bump archives
  (absent ⇒ `null`). `data/` stays raw (store the exact Markdown, never re-rendered).

Does **not**: render them in the epub (task 11), add include-toggles (task 04), or touch
`PublicationSetting`.

## Depends on
Nothing (parallel to 01, but keep 01 first so the feature's model exists).

## Key decisions already made
Overview #1 (columns on projects, project-edit home, plain Markdown), #7 (one manifest bump here).
Future "share full story" reuse is the reason they live on `Project`, not `PublicationSetting`.

## Docs to consult
`../expanded/data-model.md` §1, `../expanded/architecture.md` §D + §"Static file export" in
`documentation/architecture.md`, `documentation/export-format.md`.

## Tests to add
Extend `ProjectTest`: update persists all four; invalid Markdown ⇒
`assertSessionHasErrors('dedication')` (proves `ValidMarkdown` wired); non-owner update still 403.
Extend the export/import round-trip test: a project with all four set exports and re-imports
equal; an archive **without** the fields imports with `null`s (no crash). Update
`documentation/export-format.md` + `CHANGELOG.md`.
