# Epub Configuration — Data Model

Two persistence changes: **front-/back-matter Markdown columns on `projects`**, and a new
**`epub_settings`** table (one row per project) for the include/format choices. The rationale
for splitting content from choices is in `open-questions.md` Q1 — treat the split below as the
recommended default, not a settled fact.

## 1. New `projects` columns (Markdown content)

The project already carries the book's bibliographic fields (`author`, `publisher`, `rights`,
`isbn`, `cover_image`). The four front-/back-matter texts join them there because they are book
**content** authored once for the project, and keeping them beside the existing metadata matches
the "saved at the project level as Markdown" wording in the spec.

Migration (`add_frontmatter_to_projects_table`), all `->nullable()` `text`, placed after
`cover_image`:

| Column             | Type          | Notes                                              |
| ------------------ | ------------- | -------------------------------------------------- |
| `dedication`       | `text` null   | Markdown. Front matter.                            |
| `acknowledgements` | `text` null   | Markdown. Front matter (order vs. preface: Q5).    |
| `preface`          | `text` null   | Markdown. Front matter.                            |
| `postface`         | `text` null   | Markdown. Back matter (before any appendix).       |

- Add all four to `Project::$fillable`.
- **These stay raw Markdown**, exactly like `Scene.contents` — **not** rich HTML, **not** routed
  through `SanitizesRichHtml` (see the WYSIWYG warning in `architecture.md`). They render through
  `EpubExporter`'s own SmartPunct CommonMark converter, never `Scene::renderedContents`.
- **Import/export format impact:** `StaticSiteExporter` writes each project's `data/…` field
  files and `ProjectImporter` reads them. These four columns must be added to the project
  descriptor on **both** sides (and to `App\Support\ImportRules` / the manifest allow-list) or a
  round-trip silently drops them. Bump the export **manifest `version`** and keep the importer
  tolerant of archives that pre-date the columns (absent = `null`). This is the single biggest
  "don't forget" — see `testing.md`.

## 2. New `epub_settings` table (choices)

One row per project — `Project hasOne EpubSetting`, `EpubSetting belongsTo Project`,
`->constrained()->cascadeOnDelete()`, unique index on `project_id`.

Migration `create_epub_settings_table`:

| Column                       | Type                        | Default | Meaning                                                        |
| ---------------------------- | --------------------------- | ------- | ------------------------------------------------------------- |
| `project_id`                 | FK unique, cascade          | —       | Owning project.                                               |
| `include_project_cover`      | bool                        | `true`  | Emit the existing project cover page.                          |
| `include_scene_titles`       | bool                        | `false` | Render `Scene.name` as a heading above each scene.            |
| `include_act_descriptions`   | bool                        | `false` | Render `Act.description` on the divider page.                 |
| `include_chapter_descriptions`| bool                       | `false` | Render `Chapter.description` under the chapter heading.       |
| `include_scene_descriptions` | bool                        | `false` | Render `Scene.description` above the scene body.              |
| `include_dedication`         | bool                        | `false` | Emit the dedication front-matter page (needs non-empty text). |
| `include_acknowledgements`   | bool                        | `false` | Emit the acknowledgements page.                               |
| `include_preface`            | bool                        | `false` | Emit the preface page.                                        |
| `include_postface`           | bool                        | `false` | Emit the postface page.                                       |
| `include_author`             | bool                        | `true`  | Emit `dc:creator` when `Project.author` is set.               |
| `include_publisher`          | bool                        | `true`  | Emit `dc:publisher`.                                          |
| `include_rights`             | bool                        | `true`  | Emit `dc:rights`.                                             |
| `include_isbn`               | bool                        | `true`  | Emit the `urn:isbn:` identifier.                              |
| `chapter_title_format`       | string (enum-backed)        | `chapter_number_title` | See `ChapterTitleFormat`.                       |
| `table_of_contents_depth`    | string (enum-backed)        | `chapters` | See `TableOfContentsDepth`.                              |
| `divider_type`               | string (enum-backed)        | `horizontal_rule` | See `DividerType`.                                 |
| `include_codex_appendix`     | bool                        | `false` | Emit the codex appendix.                                       |
| `appendix_entry_types`       | json (array of CodexEntryType values) | `[]` | Which codex types to include.                        |
| `appendix_include_images`    | bool                        | `false` | Embed each entry's first image.                               |

> [!IMPORTANT]
> **The defaults reproduce today's output.** Metadata toggles default `true` (current behaviour
> emits everything present); every *new* rendering (`scene_titles`, all `descriptions`, front/back
> matter, appendix) defaults **off**; `chapter_title_format` defaults to the current
> `"Chapter n: name"`; `divider_type` defaults to the current `<hr/>`. A project with a
> default-constructed `EpubSetting` must export identically to v1 — `testing.md` guards this.

### Model & casts

`app/Models/EpubSetting.php` — `$fillable` for every column above; `$casts`:

```php
protected $casts = [
    'include_project_cover' => 'boolean',
    // … every include_* boolean …
    'chapter_title_format' => ChapterTitleFormat::class,
    'table_of_contents_depth' => TableOfContentsDepth::class,
    'divider_type' => DividerType::class,
    'appendix_entry_types' => AsEnumCollection::class.':'.CodexEntryType::class,
];
```

(Use `AsEnumCollection` so `appendix_entry_types` round-trips as typed `CodexEntryType` values;
if that cast proves fiddly against SQLite JSON, fall back to `'array'` and map in the service.)

### Accessor — never a null-object surprise

Follow the lazy-singleton spirit of `CrawlerSetting::current()` / `ImportSetting::current()`,
but **scoped to a project**. Add to `Project`:

```php
public function epubSetting(): HasOne
{
    return $this->hasOne(EpubSetting::class);
}

/** Existing row, or an unsaved default instance — never null. */
public function epubSettingOrDefault(): EpubSetting
{
    return $this->epubSetting ?? $this->epubSetting()->make();
}
```

The **export path** uses `epubSettingOrDefault()` so a project that never visited the config
form still exports (with defaults). The **config form** `firstOrNew`s on save. Do **not**
auto-create the row in `Project::booted()` — an unsaved default keeps the DB clean and makes the
"defaults === v1" regression test trivial. (Grill: `booted()` create vs. lazy — Q2.)

## 3. New Enums (`app/Enums`)

Follow the `CodexEntryType` / `BookLanguage` pattern: string-backed, `label()`, plus behaviour.

### `ChapterTitleFormat`
Cases and the exact string each produces for position `12`, name `"The Storm"`:

| Case                    | Value                  | Rendered            |
| ----------------------- | ---------------------- | ------------------- |
| `ChapterNumberTitle`    | `chapter_number_title` | `Chapter 12: The Storm` |
| `NumberTitle`           | `number_title`         | `12: The Storm`     |
| `ChapterNumber`         | `chapter_number`       | `Chapter 12`        |
| `Number`                | `number`               | `12`                |
| `Title`                 | `title`                | `The Storm`         |

Add `format(int $position, ?string $name): string`. It is the **single source of truth** used by
both the chapter page heading and the TOC/nav labels (today `EpubExporter::chapterNavTitle()`
hard-codes the format — replace it with a call here). Guard the name-less case (a blank name in
`Title`/`…Title` formats must not leave a dangling `": "`).

### `TableOfContentsDepth`
`Acts` (`acts`), `Chapters` (`chapters`), `Scenes` (`scenes`). Drives both the in-book `toc.xhtml`
page **and** the EPUB 3 nav document depth. `Scenes` requires scene-level anchors (see
`architecture.md`). `label()` for the form.

### `DividerType`
`HorizontalRule` (`horizontal_rule` → `<hr/>`), `Decorative` (`decorative` → a centered ornament,
e.g. `<p class="divider">* * *</p>` styled by `styles.css`). `Image` is **V2** — do not add the
case yet, or add it and reject it in validation (Q6).

## Invariants & interactions

- **Existing invariants untouched:** main plotline, `position` ordering, authorization-via-project
  all stand. The `filteredTree()` skip-empty rule is unchanged — scene titles/descriptions are
  rendered *within* surviving chapters only.
- **`include_*` + empty content = no page.** A front-matter page is emitted only when its toggle is
  on **and** the underlying text is non-empty, so a checked "dedication" with no text never ships a
  blank page. Same guard for descriptions.
- **Appendix + no types selected = no appendix**, even if `include_codex_appendix` is true.
- **Deletion:** `epub_settings` cascades on project delete via the FK; no `booted()` cleanup
  needed (unlike cover files, this is pure relational data).
