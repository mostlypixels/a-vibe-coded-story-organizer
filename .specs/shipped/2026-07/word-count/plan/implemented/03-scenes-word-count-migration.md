# Task 3 — `scenes.word_count` column, index and backfill

## Scope

`database/migrations/XXXX_XX_XX_add_word_count_to_scenes_table.php`:

```php
$table->unsignedInteger('word_count')->default(0)->after('contents');
$table->index(['chapter_id', 'word_count']);
```

Then backfill existing rows in the same `up()`, chunked:

```php
Scene::query()->select('id', 'contents')->chunkById(500, function ($scenes) {
    foreach ($scenes as $scene) {
        DB::table('scenes')->where('id', $scene->id)->update([
            'word_count' => WordCounter::count($scene->contents, FieldKind::Markdown),
        ]);
    }
});
```

`down()` drops the index then the column.

## Depends on

Task 2.

## Key decisions already made

* **`unsignedInteger`, `default(0)`, never nullable** — 0 is a real answer; `NULL` would
  make every caller handle "unknown".
* **Composite `['chapter_id', 'word_count']`** — makes the per-chapter `SUM` covering, so
  the aggregate never touches the `contents` blob.
* **No column on `chapters` / `acts` / `projects`.** Totals are computed. Adding one later
  is a migration plus a backfill, so nothing here forecloses it.

> [!WARNING]
> **Backfill with raw `DB::table()->update()`, never `$scene->save()`.** A model save fires
> `HasRevisions` and would write a revision row per scene — a migration inventing thousands
> of edits nobody made, polluting every history page — and would bump `updated_at`. This is
> the single easiest thing to get wrong in this task.

## Consult

`../expanded/data-model.md`.

## Tests

`tests/Feature/AddWordCountToScenesMigrationTest.php`, following
`BackfillBaselineRevisionsMigrationTest`:

* A scene created before the migration gets the correct count after it.
* A scene with `NULL`/empty contents gets `0`.
* **The backfill writes no `revisions` rows** — count before and after.
* **The backfill does not change `updated_at`.**
* `down()` then `up()` leaves the column and counts correct.
