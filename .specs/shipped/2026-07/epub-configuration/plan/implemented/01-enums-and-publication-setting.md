# 01 — Enums & PublicationSetting model

**Model:** Haiku (mechanical, fully specified, unit-tested in isolation).

## Scope
Lay the data + type foundation with **zero behaviour change** anywhere else.

- Three string-backed enums in `app/Enums` (pattern: `CodexEntryType`/`BookLanguage` — `label()`
  + behaviour):
  - `ChapterTitleFormat`: cases `ChapterNumberTitle` (`chapter_number_title`), `NumberTitle`
    (`number_title`), `ChapterNumber` (`chapter_number`), `Number` (`number`), `Title` (`title`),
    plus `format(int $position, ?string $name): string` — the **single source** for the chapter
    heading and its nav/TOC label. Guard the name-less case (no dangling `": "`).
  - `TableOfContentsDepth`: `Acts` (`acts`), `Chapters` (`chapters`), `Scenes` (`scenes`).
  - `DividerType`: `HorizontalRule` (`horizontal_rule` → `<hr/>`), `Decorative` (`decorative` →
    ornament snippet). **No `Image` case** (V2). Expose the divider HTML from the enum.
- Migration `create_publication_settings_table` + `app/Models/PublicationSetting.php` per
  `data-model.md` (rename EpubSetting→PublicationSetting, table `publication_settings`): FK
  `project_id` unique cascade; all `include_*` booleans with the defaults in the overview;
  `chapter_title_format` / `table_of_contents_depth` / `divider_type` enum-cast columns;
  `section_order` json (default `[title, dedication, acknowledgements, preface, toc, body,
  postface, appendix]`); `appendix_entry_types` json (default `[]`); `appendix_include_images`
  bool. `$fillable` + `$casts` (enum casts; `section_order`→`array`; `appendix_entry_types`→
  `AsEnumCollection:CodexEntryType` or `array` if the cast fights SQLite).
- `Project`: `publicationSetting(): HasOne` + `publicationSettingOrDefault(): PublicationSetting`
  (returns `$this->publicationSetting ?? $this->publicationSetting()->make()`).

Does **not**: touch the exporter, the config UI, the archive, or `Project`'s columns (task 02).
Does **not** auto-create the row (`booted()`) — lazy only.

## Depends on
Nothing.

## Key decisions already made
Overview #2 (lazy), #3 (defaults===v1), #4 (section_order shape + membership), #10-name resolved
to **PublicationSetting**. Enums own their formatting (no magic strings).

## Docs to consult
`../expanded/data-model.md` (columns, casts, accessor), `../expanded/architecture.md` §"New enums".

## Tests to add
`tests/Unit/ChapterTitleFormatTest` — every case's `format(12,'The Storm')` string + the
name-less/blank-name guard. `PublicationSettingTest` (unit/model) — default attribute values
match the overview; enum + json casts round-trip; `publicationSettingOrDefault()` returns an
**unsaved** default when no row exists and the persisted row when one does.
