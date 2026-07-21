# 03 — Split Export & import into three server-rendered pages

## Scope
Replace the single Alpine-tabbed `admin/data/index.blade.php` with three pages reached by
server-rendered sub-nav links (spec: "no longer javascript tabs, but separate controller
actions"). **No config behaviour yet** — this is purely the structural split; the zip export and
import must behave exactly as before.

- Routes (`routes/web.php`, `admin.` group): `GET /data` → `redirect()->route('admin.data.export-project')`
  (keep name `data.index`); `GET /data/export/project` (`data.export-project`);
  `GET /data/export/ebook` (`data.export-ebook`); `GET /data/import` (`data.import.index`).
  Keep every existing **POST** route/name (`data.export`, `data.export.epub`, `data.import`,
  `data.imports.*`, `data.import-settings`).
- `DataTransferController`: three thin GET actions — `exportProject()` (user's projects for the
  `.zip` form), `exportEbook()` (projects; the config form itself lands in task 04 — for now a
  minimal page hosting the existing epub download form), `import()` (`ImportSetting::current()` +
  non-completed imports).
- Views under `resources/views/admin/data/`: split `export-project.blade.php`,
  `export-ebook.blade.php`, `import.blade.php` out of the old index (move the panels **verbatim**),
  each `@include('admin.data.partials.subnav')`. New `partials/subnav.blade.php` = three `<a>`
  links with `aria-current="page"` via `request()->routeIs(...)` (reuse `sidebar.blade.php`'s
  active-state idiom; drop all `role="tablist"`/roving-tabindex machinery). Delete
  `index.blade.php`.

Does **not**: add the config form/persistence (task 04), change what the exports produce.

## Depends on
Nothing (can run before or after 01/02).

## Key decisions already made
Overview #8 (links not tabs; names stay `admin.data.*` so the sidebar entry stays active). The
Import-settings card + flash block move onto the pages that redirect back (import + config-save).

## Docs to consult
`../expanded/architecture.md` §A, `../expanded/ui.md` §"Page structure".

## Tests to add
`DataTransferTest`: each of the three GET pages `assertOk` for an authed user and shows its own
controls; `admin.data.index` redirects to `export-project`; sub-nav marks the current page
`aria-current="page"` and the sidebar "Export & import" entry stays active on all three.
**Update existing `ExportTest`/`ImportTest`/`ImportSettingTest`** to the new page URLs where they
asserted against the old tabbed index (behaviour identical). `composer test` green.
