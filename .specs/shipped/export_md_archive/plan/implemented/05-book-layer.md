# Task 05 — book/ reading layer (TOC + compiled chapters)

## Scope

Add the human-readable `book/` folder to the zip: a table of contents plus one compiled HTML
page per chapter, with reading navigation. This is the only **rendered** layer (it runs
`Str::markdown()` on scene `contents`); it holds no descriptions, notes, images, or `data/`.

**This task builds:**

- **`book/index.html`** — the TOC. Lists each **act** (title as a section heading) with its
  **chapters** (titles) as links to the chapter files, in `position` order.
- **`book/NN/`** — one folder per act, named by the act's **zero-padded position**
  (`01`, `02`, …).
- **`book/NN/NN.html`** — one file per chapter, named by the chapter's **zero-padded,
  per-act position**. Each page contains:
  - the chapter title,
  - each scene's `contents` rendered `{!! Str::markdown($scene->contents ?? '') !!}` in
    `position` order, **joined by `<hr>`** (no scene titles),
  - **prev/next** chapter links at **both top and bottom**, following global reading order
    **across act boundaries** (last chapter of act *n* → first chapter of act *n+1*). The
    first chapter's "prev" and the last chapter's "next" link to `../index.html` (the TOC).
    Mind the relative paths (chapter pages are one level below `index.html`, and prev/next may
    cross into a sibling act folder, e.g. `../02/01.html`).
  - minimal inline CSS (readable body font, `max-width`) — a small shared partial is fine.
- Render via Blade templates under `resources/views/exports/book/` (`index.blade.php`,
  `chapter.blade.php`, optional `layout.blade.php`) rendered to string with
  `view(...)->render()` — keep HTML in Blade, not string-built in the service (guidelines).
- Build a flat, ordered **chapter sequence** (all chapters across all acts, in reading order)
  once, so prev/next and the TOC share it.
- **Finalize docs**: `documentation/architecture.md` *Static file export* section (two
  layers, the `StaticSiteExporter` service, the auth exception, async-readiness); complete the
  `CHANGELOG.md` `Added` entry.

**This task does NOT build:** any `data/` content (02–04). It reuses the act→chapter→scene
loading from task 02 (reload or share a private loader).

## Depends on

Task **01** (exporter + endpoint). Order after **02** to reuse the tree loading; does not
depend on 03/04.

## Key decisions already made (binding)

- `book/` = TOC + per-chapter compiled pages; scenes joined by `<hr>`, no scene titles.
- Zero-padded act position folders; zero-padded **per-act** chapter position files.
- Prev/next cross act boundaries; ends link to the TOC.
- `contents` is rendered with `Str::markdown()` (the app's existing render path in
  `story/index` and `shared/scenes/show`); `book/` never includes descriptions/notes/images.
- Minimal inline CSS only.

## Docs to consult

- `plan/00-overview.md` (book layout + numbering).
- `resources/views/story/index.blade.php` and `resources/views/shared/scenes/show.blade.php`
  for the exact `Str::markdown()` render path to mirror.

## Tests (extend `tests/Feature/ExportTest.php`)

- **TOC**: `book/index.html` lists each act title and links to every chapter file
  (`01/01.html`, `01/02.html`, `02/01.html` …) in order.
- **Chapter page**: `book/01/01.html` contains the chapter title and each scene's rendered
  prose (assert a `<p>`/`<em>` produced from Markdown), with an `<hr>` between two scenes.
- **Numbering**: two acts, chapters per-act → files land at `book/01/01.html`,
  `book/01/02.html`, `book/02/01.html` (per-act, zero-padded). Reordering positions
  renumbers.
- **Prev/next**: middle chapter links to the correct previous and next chapter (including the
  **cross-act** hop from the last chapter of act 1 to the first of act 2); the very first
  chapter's prev and the very last chapter's next point to `index.html`.
- **Empty project**: `book/index.html` renders with no chapters and no chapter files, no
  error.
