# Word Count — data model

## Migration

`database/migrations/XXXX_XX_XX_add_word_count_to_scenes_table.php`

```php
$table->unsignedInteger('word_count')->default(0)->after('contents');
$table->index(['chapter_id', 'word_count']);   // covers the per-chapter SUM
```

* `unsignedInteger` — a word count is never negative; 4 B ceiling is ~4.3 billion words.
* `default(0)` — an existing row is 0 until backfilled, never `NULL`. No caller has to
  handle "unknown".
* The composite index is what makes the grouped `SUM` covering: the aggregate reads the
  index, not the row (and never the `contents` blob).

## Backfill

Same migration, in `up()`, after the column exists — chunked, mirroring
`2026_07_22_000002_backfill_baseline_revisions`:

```php
Scene::query()->select('id', 'contents')->chunkById(500, function ($scenes) {
    foreach ($scenes as $scene) {
        // Direct UPDATE, not ->save(): no revision rows, no touched timestamps.
        DB::table('scenes')->where('id', $scene->id)
            ->update(['word_count' => WordCounter::count($scene->contents, FieldKind::Markdown)]);
    }
});
```

> [!WARNING]
> Backfill with a raw `DB::table()->update()`, never `$scene->save()`. A model save fires
> `HasRevisions` and would write a revision row per scene — a migration inventing thousands
> of "edits" nobody made, polluting every history page. It would also bump `updated_at`.

## No column on chapters / acts / projects

Deliberate. Totals are computed (`architecture.md`). Adding one later is a migration plus a
backfill, so nothing here forecloses it.

> [!IMPORTANT]
> If a future change does denormalise upward, it must handle **`ReparentsChildren`** (a
> scene moving between chapters) and the move-or-cascade delete in
> `DestroyChapterRequest` / `DestroyActRequest` — both change a scene's ancestors without
> touching the scene's own text, which is exactly the class of path that gets forgotten.

## Invariant

**`scenes.word_count` always equals `WordCounter::count($scene->contents)` for the stored
value.** Held by a model hook, not by callers — see `architecture.md`. That is the only
invariant this feature adds; everything above it is derived.

## Seeding

`MelusineSeederEn/Fr/It` write scenes through the model, so the hook fills `word_count` with
no seeder change. `DatabaseSeederTest` should assert one seeded scene has a non-zero count —
cheap proof the hook survives a path nobody thinks about.

## Import / export

* **Import** (`ProjectGraphImporter`) creates scenes through the model → hook fires → counts
  correct with no importer change. Confirm rather than assume: the importer is a place where
  bulk `insert()` would bypass the hook.
* **Export** (`StaticSiteExporter`, `EpubExporter`, project zip): `word_count` is derived,
  so it is **not** exported and not read on import. Recomputed from `contents` on arrival,
  which is the only source that can be trusted across versions of the counting rule.
