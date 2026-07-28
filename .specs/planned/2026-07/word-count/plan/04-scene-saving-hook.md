# Task 4 — Keep `scenes.word_count` true on every write path

## Scope

In `Scene::booted()`:

```php
static::saving(function (Scene $scene): void {
    if ($scene->isDirty('contents')) {
        $scene->word_count = WordCounter::count($scene->contents, FieldKind::Markdown);
    }
});
```

That is the whole change. Add `word_count` to `$fillable`/casts only if the model's existing
style requires it.

## Why a model hook and not a controller

`$model->save()` is the one thing every write path shares:

| Path | Where |
|---|---|
| Autosave PATCH | `FieldAutosaveController::update()` |
| Manual save | `SceneController::update()` |
| Revert a field | `RevisionReverter::restore()` |
| Undo a whole save | `RevisionReverter::revertSave()` → `restore()` |
| Import | `ProjectGraphImporter` |
| Seeders / factories | `MelusineSeeder*`, `SceneFactory` |

> [!IMPORTANT]
> A controller-level implementation would leave the count stale after a **revert** —
> `RevisionReverter` never touches `FieldAutosaveController`. A count that is right until
> someone uses Undo is the worst version of this feature. Tests 3 and 4 below exist to fail
> in exactly that case.

This is the sanctioned exception in `CLAUDE.md`: *invariants and lifecycle* belong in the
model. Same shape as the existing `position` auto-assignment.

## Depends on

Task 3.

## Key decisions already made

* **`saving`, not `saved`** — sets an attribute on the row being written, so no second
  `UPDATE` and no half-applied state.
* **`isDirty('contents')`** — renaming a scene must not re-count its prose.
* Only `contents`. Never `description`, never `notes`.

## Consult

`../expanded/architecture.md`, `../expanded/overview.md` (acceptance criterion 2).

## Tests

`tests/Feature/WordCountTest.php` — the invariant, once per write path. Each asserts the
stored column matches `WordCounter::count()` of the stored `contents`:

1. Manual save via `SceneController::update()`.
2. **Autosave PATCH** via `FieldAutosaveController` — what the writer actually uses.
3. **Revert a single field** via `revisions.revert`.
4. **Undo a whole save** via `revisions.saves.revert`.
5. Project import via `ProjectGraphImporter`.
6. `Scene::factory()->create()` and a seeded scene (`DatabaseSeederTest` may be the better
   home for the seeder half).
7. Renaming a scene (`name` only) leaves `word_count` untouched — proves the `isDirty` guard.
8. Emptying `contents` sets it to `0`.

> [!NOTE]
> Verify tests 3 and 4 actually fail without the hook. Patch the hook out, run them, restore.
> `CLAUDE.md` asks for a test that fails before the fix; for this task that is the only thing
> distinguishing a regression test from one that happens to pass.
