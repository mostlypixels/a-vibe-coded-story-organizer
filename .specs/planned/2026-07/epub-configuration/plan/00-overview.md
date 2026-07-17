# Epub Configuration — Plan Overview

The manual for this feature's implementation. **Never implemented or moved.** Read it, plus
`../expanded/*.md` and the `resolution-log.md`, before touching any task.

## Execution order

| # | Task | Purpose |
|---|------|---------|
| 01 | enums-and-publication-setting | The three enums + `PublicationSetting` model/migration + `Project` relation + lazy `publicationSettingOrDefault()`. No behaviour change. |
| 02 | project-frontmatter-fields | Four Markdown columns on `projects` + project-edit UI + validation + archive round-trip (owns the **one** manifest-version bump). |
| 03 | split-export-import-pages | Replace the Alpine-tabbed page with three server-rendered pages + sub-nav + routes + thin GET actions. Behaviour of zip/import unchanged. |
| 04 | publication-setting-config-form | The Export-ebook config form + `PublicationSettingController@update` + request + persistence (toggles, enums, sortable order, appendix types). Exporter not yet consuming. |
| 05 | publication-setting-in-archive | `PublicationSetting` travels in the export `.zip` and is restored on import (strict-validate, fall back to defaults on malformed). |
| 06 | shared-cover-image-service | Extract `CoverImageService`; refactor `ProjectController` + `EpubExporter` cover handling onto it. No behaviour change. |
| 07 | chapter-cover-infrastructure | `chapters.cover_image` column, upload/remove UI, cascade orphan cleanup, archive support. |
| 08 | exporter-metadata-and-cover-toggles | Thread the setting into `EpubExporter`; metadata + project-cover toggles; **defaults===v1** regression guard. |
| 09 | exporter-content-options | Chapter-title format, scene titles, act/chapter/scene descriptions (adds `RichText::toXhtmlFragment()`), divider type. |
| 10 | exporter-toc-depth | TOC/nav depth acts/chapters/scenes, with a library-capability spike + scene-anchor fallback. |
| 11 | exporter-front-back-matter | Render dedication/acknowledgements/preface/postface honouring the sortable section order. |
| 12 | exporter-chapter-cover-pages | Full-page chapter cover images in the epub, gated by `include_chapter_covers`. |
| 13 | codex-appendix | The codex appendix (types, ordering, first image, rich-HTML→XHTML). Final, heaviest slice. |

## Model assignment (per-task, decided with the user)

Run each task's `plan-implementer` on the model below (pass `model:` when spawning the agent).
Unlisted = the **Sonnet** default. Fable is deliberately used **nowhere** (no coding edge here;
quota risk).

| Model | Tasks |
|-------|-------|
| **Opus** | 05 (untrusted archive config), 07 (cascade cleanup + import sniff), 09 (HTML→XHTML edge cases), 10 (library spike + fallback), 13 (appendix, heaviest) |
| **Haiku** | 01 (enums/model, mechanical), 06 (service extraction, test-guarded) |
| **Sonnet** | 02, 03, 04, 08, 11, 12 |

## Binding design decisions (do not re-litigate)

1. **Markdown fields live on `projects`**, edited on the **project edit page** (plain textarea +
   `ValidMarkdown`, grouped under "Book front & back matter (Markdown)"). The ebook config page
   shows only the include-toggles + help text pointing to the project edit page. They stay raw
   Markdown (like `Scene.contents`), rendered through `EpubExporter`'s SmartPunct converter —
   never rich HTML, never the sanitizer.
2. **`PublicationSetting`** (table `publication_settings`), one row per project, **lazy**:
   `Project::publicationSettingOrDefault()` returns an unsaved default when no row exists. No
   backfill migration. Never auto-created in `booted()`.
3. **Defaults reproduce today's output byte-for-byte.** Metadata/cover toggles default `true`;
   every new rendering (scene titles, all descriptions, front/back matter, appendix, chapter
   covers) defaults **off**; `chapter_title_format` = `chapter_number_title`
   (`"Chapter n: name"`); `divider_type` = `horizontal_rule`; `table_of_contents_depth` =
   `chapters`. Task 08 owns the **defaults===v1 regression test** — every later exporter task
   must keep it green.
4. **Section order is a sortable list** stored as an ordered JSON array of component keys on
   `PublicationSetting`: `[title, dedication, acknowledgements, preface, toc, body, postface,
   appendix]`. `title` is pinned first; `toc` and `body` are movable. Reordered with the existing
   **move-up/move-down** idiom (acts/chapters/scenes), not drag-and-drop. Include-toggles gate a
   section on/off **independently** of its position; a section renders only when it is enabled
   **and** has non-empty content. Project cover (reader-placed by the library) and chapter covers
   (inline before each chapter within `body`) are **not** list entries.
5. **Covers: project + chapter, via a shared `CoverImageService`** (store/delete on the `public`
   disk + epub byte-read). `image` divider and per-scene images are **V2** — not built here.
6. **Rich HTML → XHTML** goes through one shared `RichText::toXhtmlFragment()` helper
   (`DOMDocument` load-HTML → save-XML on the body fragment), introduced in task 09 and reused by
   the appendix (task 13). Descriptions and codex entries are sanitized **rich HTML**, not
   Markdown — embedding them raw would break `validatePackage()`.
7. **Archive carries the config.** One manifest-version bump (owned by task 02) covers every new
   field: the four Markdown columns, chapter covers, and the serialized `PublicationSetting`. On
   import, the config is **validated, not trusted**: a malformed config falls back to a default
   `PublicationSetting` and the content still imports; forged media still rejects the archive
   (existing `ArchiveValidator` content-sniff, extended to chapter covers).
8. **The page split uses server-rendered links, not Alpine tabs** (spec's "separate controller
   actions"). Route names stay under `admin.data.*` so the sidebar entry stays active.

## Invariants every task must preserve

- **Authorization walks `ProjectPolicy`.** Every new read authorizes `view`, every write
  authorizes `update`, on the owning project — mirrored in the Form Request. Non-owner = 403,
  covered by a test. `PublicationSetting`/`Project` writes are `update`; export/config-read is
  `view`. No new policy needed.
- **`EpubExporter` render isolation** (existing grilled rules, unchanged): scene bodies and the
  Markdown matter render through the service's **private SmartPunct `CommonMarkConverter`**, never
  `Scene::renderedContents`; **all** HTML lives in Blade under `resources/views/exports/epub/`,
  never string-built in PHP; every shipped `.xhtml` stays well-formed and the OPF stays
  schema-valid (`validatePackage()` is the hard gate).
- **`position` ordering + main-plotline + bookend** invariants are untouched. `filteredTree()`'s
  skip-empty rule stands; new per-level rendering happens only within surviving chapters.
- **No magic strings.** Formatting/ordering/divider logic lives on the enums, not `match` on raw
  strings in the service or views (`ChapterTitleFormat::format()` is the single source for both the
  chapter heading and its nav/TOC label).
- **`documentation/` + `CHANGELOG.md`** updated as architecture changes (the exporter section, a
  new "Publication settings" section, export-format.md for the new archive fields).
