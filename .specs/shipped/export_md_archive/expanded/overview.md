# Export to static files — Overview

## Problem statement

A writer wants a portable, offline, plain-file copy of a project: the manuscript as
browsable HTML, the raw prose as Markdown (with metadata), and the images. Today the
Admin → **Export & import** section (`resources/views/admin/data/index.blade.php`) is a
"coming soon" stub. This feature fills the **Export** tab so a user can download their
project as a `.zip` of static files whose folder layout mirrors the app's navigation.

## Goals

- From Admin → Export & import → **Export**, produce a `.zip` download of one project's
  content as static files.
- A folder tree that mirrors the app menu's **Story** section: a directory per act, a
  directory per chapter inside it, and one file per scene inside that — each named
  `NN-slug` where `NN` is the position and `slug` is `Str::slug()` of the title.
- Each act directory and chapter directory holds an `index.html` (the entity's own HTML:
  name + rich-HTML description).
- Each scene produces **two** files: `NN-slug.html` (rendered HTML of *all* scene fields)
  and `NN-slug.md` (Markdown `contents` body with a YAML frontmatter metadata block).
- A **compiled storyline** HTML file at the project root (the folder that contains the act
  directories) — the whole manuscript's prose concatenated in reading order.
- Images exported in their **original stored format** (no thumbnails), with a machine- and
  human-readable way to connect each image back to the entity it belongs to and the field
  (collection) it fills (e.g. `cover`).
- A toggle on the form to **include images or not**.
- HTML is exported "as is": already-sanitized rich-HTML fields (`description`, `notes`) are
  written verbatim; Markdown `contents` is rendered with `Str::markdown()` for the `.html`
  files and left raw in the `.md` files.

## Non-goals

- **Import / round-trip.** This is a one-way static export, not a backup format. The Import
  tab stays a stub. There is no guarantee the export can be re-imported.
- **Thumbnails or image transforms.** Originals only (matches how `CodexMedia` already
  stores files — there is no thumbnail pipeline).
- **A new visual theme / print stylesheet.** The exported HTML reuses the existing render
  paths (`Str::markdown()`, `x-rich-text`); polished standalone CSS is out of scope for v1
  (see `open-questions.md` Q6).
- **No database schema changes.** This is a pure read-and-render feature.

## User stories

- As a writer, I open Admin → Export & import → Export, pick a project, choose whether to
  include images, click **Export**, and my browser downloads `my-project.zip`.
- As a writer, I unzip it and browse `storyline.html` to read the whole manuscript, or open
  `01-the-beginning/01-arrival/01-the-door.html` to read one scene with all its fields.
- As a tool author, I read the `.md` files' frontmatter to re-assemble the project
  elsewhere, and read `images/manifest.json` to know that `images/.../cover/...jpg` is the
  cover of the "Alice" character entry.

## Acceptance criteria

1. `GET /admin/data` shows a working **Export** form: a project selector and an
   include-images toggle, submitting to a new export route.
2. Submitting returns a `.zip` download (`Content-Type: application/zip`,
   `Content-Disposition: attachment; filename="<project-slug>.zip"`).
3. The zip's tree matches the layout in `architecture.md` → *Export artifact layout*:
   `NN-slug` act dirs → `NN-slug` chapter dirs → `NN-slug.html` + `NN-slug.md` scenes, each
   act/chapter dir with an `index.html`, and a root `storyline.html`.
4. Scene `.html` contains name, description (rich HTML verbatim), contents (Markdown→HTML),
   notes (rich HTML verbatim), status and event — **all** fields.
5. Scene `.md` has YAML frontmatter (metadata) followed by the raw Markdown `contents`.
6. With the toggle **on**, images are present in their original format with a manifest
   connecting each to its entity + collection. With the toggle **off**, no `images/` folder
   is written and no image bytes are in the zip.
7. Authorization: a user can only export a project they own; selecting another user's
   project returns 403. Unauthenticated users are redirected to login.
8. Empty project (no acts) still exports successfully: a valid zip with a `storyline.html`
   (empty/placeholder body) and no act folders.
9. A feature test (`tests/Feature/ExportTest.php`) covers happy path, structure,
   image on/off, authorization, and validation.

## Where this sits in the pipeline

Grill `open-questions.md` **first** — the project-scope, menu-coverage, and MD-frontmatter
questions materially change the task breakdown. Then run `plan-tasks export_md_archive`.
