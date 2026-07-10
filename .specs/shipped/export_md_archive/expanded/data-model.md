# Export to static files — Data model

> [!NOTE]
> This feature adds **no migrations and no new tables**. It is a pure read-and-render
> pipeline over the existing schema. This page documents the read model, the one new
> runtime dependency, and the image-ownership fact that shapes the export tree.

## Read model (what the exporter loads)

The export walks one `Project` and eager-loads the tree the Story overview already renders,
plus codex media for images:

```php
$project->load([
    'acts' => fn ($q) => $q->orderBy('position'),
    'acts.chapters' => fn ($q) => $q->orderBy('position'),
    'acts.chapters.scenes' => fn ($q) => $q->orderBy('position'),
    'acts.chapters.scenes.event',      // scene .html/.md "event" field
    'codexEntries.media',              // images + their collection/entity
]);
```

- Ordering is by `position` at every level — the **same invariant** the Story overview
  relies on (`StoryController::index`, `app/Http/Controllers/StoryController.php`). Position
  is unique per parent (assigned in each model's `booted()` `creating` hook), so the
  numeric `NN-` filename prefix is collision-free within a directory (see Q4).
- Scene fields exported: `name`, `description` (rich HTML), `contents` (Markdown),
  `notes` (rich HTML), `status` (`SceneStatus` enum → `label()`), and `event` (optional).
- Eager-load to avoid N+1 across the act → chapter → scene tree (CLAUDE.md → Database).

## Image ownership — the fact that shapes the tree

Images live in **exactly one place**: the `codex_media` table, owned by `CodexEntry` (not by
scenes/acts/chapters/projects). See `app/Models/CodexMedia.php` and `CodexEntry::media()`.
Each row carries:

| Column          | Meaning                                                        |
|-----------------|---------------------------------------------------------------|
| `collection`    | `CodexMediaCollection`: `cover` / `reference_image` / `reference_file` — **this is the "field name" the spec means by "ie: cover"** |
| `path`          | Storage path on the `public` disk (`codex-media/…`)           |
| `original_name` | The uploaded filename — reuse verbatim for the export filename |
| `mime_type`     | For the manifest                                              |
| `codex_entry_id`| The owning entry (→ entity type + name via `CodexEntry`)       |

> [!IMPORTANT]
> Because images belong to **codex entries**, not to the act/chapter/scene tree, they cannot
> be nested inside the Story folders. They need their own top-level `images/` folder plus a
> manifest that links each file back to `{entity, collection}`. This is exactly what the spec
> asks for: *"a way to connect the images to the entity they belong to, and the field name."*
> See `architecture.md` → *Images & manifest* and Q2/Q3 in `open-questions.md`.

- Rich-HTML fields (`description`, `notes`) **cannot** contain `<img>` — the sanitizer
  allow-list (`RichTextFields::ALLOWED_TAGS`) excludes it. Scene `contents` is Markdown and
  *could* theoretically contain `![](…)` image syntax, but there is no in-prose image upload
  in v1, so **codex media is the only image source**. Note this assumption; revisit if
  in-prose images ship.
- Files are read straight off `Storage::disk('public')` and copied byte-for-byte into the
  zip ("initial format, no thumbnails"). No thumbnail pipeline exists to bypass.

## New dependency: `ext-zip`

Building the archive uses PHP's `ZipArchive`, which requires the **`ext-zip`** extension.
`composer.json` currently declares only `"php": "^8.2"` — no `ext-zip`.

- Add `"ext-zip": "*"` to `composer.json` `require` so the dependency is explicit (CLAUDE.md:
  configuration/deps in one place; the `harden_deps` spec set this precedent).
- CI / in-memory SQLite test runner must have `ext-zip` enabled (it is bundled with most PHP
  builds and already enabled in typical dev images — confirm in the plan).
- No external Composer package is added; `ZipArchive` is core PHP. See Q5 for the
  streaming-vs-temp-file tradeoff.
