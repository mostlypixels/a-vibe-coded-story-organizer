# Codex plan — 07 · Media: uploads, cover, reference images & files

## Goal

Entries get their right column: a single cover image, reference image gallery, and reference file list — uploaded, replaced, and removed through the entry form's one Save, with files cleaned off disk on every delete path.

## Depends on

03 (entry form + requests).

## Spec references

- [`../data-model.md`](../data-model.md) — `codex_media` schema, single-cover rule, file-cleanup warning.
- [`../architecture.md`](../architecture.md) — `CodexMediaService`, centralized upload rules.
- [`../ui.md`](../ui.md) — right column.

## Files to create/modify

### `app/Services/CodexMediaService.php`

- `storeCover(CodexEntry, UploadedFile)` — replaces any existing `Cover` row: delete old row **and** its file, insert new (exactly one Cover row at all times; exposed via the `CodexEntry::cover()` hasOne — there is no FK column).
- `storeMany(CodexEntry, CodexMediaCollection, UploadedFile[])` — stores on the `public` disk; `position` continues per **(entry, collection)** (the model hook from task 01 handles it, but the service must not fight it under bulk insert).
- `remove(CodexMedia)` — deletes row + file.
- `purge(CodexEntry)` — deletes **all files** for an entry; called from a `CodexEntry` `deleting` hook (or the destroy path) **before** the FK cascade drops the rows — this closes task 03's destroy TODO.
- Centralizes the storage path/naming — nothing hard-coded in controllers.

### Upload rules — centralized

One shared definition (a `config/codex.php` block or a constants class à la `App\Support\PlotlineColors`) for allowed mimes + max KB per collection, referenced by **both** `StoreCodexEntryRequest` and `UpdateCodexEntryRequest`: `cover` single image, `reference_images[]` images, `reference_files[]` files (choose a sane allowlist — pdf/txt/md/doc(x) — not `*`), plus `remove_media[]` array of ids that must belong to the entry (`Rule::exists` scoped).

### Controller & views

- `CodexEntryController@store/@update` — hand validated uploads and `remove_media[]` to the service inside the existing transaction (files after commit or tolerant of rollback — keep it simple: write files last).
- `resources/views/codex/edit.blade.php` / `create.blade.php` right column — replace the placeholder: current cover thumbnail + file input, reference-image gallery and file list with per-item "remove" **checkboxes/toggles feeding hidden `remove_media[]` inputs** (single-save; decided), accepted-types/size hints from the shared config, `alt` falling back to the entry name, download links via `original_name`.
- `resources/views/codex/index.blade.php` — add the cover thumbnail column.
- The entry `<form>` already has `enctype="multipart/form-data"` from task 03's shell — verify.

## Key decisions already made

`public` disk + `storage:link` (v1 accepts publicly reachable URLs — [`../open-questions.md`](../open-questions.md) #10); single-save with `remove_media[]` (no per-item AJAX); cover identified by collection, not a FK.

## Tests — `tests/Feature/CodexMediaTest.php`

`Storage::fake('public')` throughout, per [`../testing.md`](../testing.md): cover upload creates the single Cover row (`cover()` resolves); re-upload replaces the row and **deletes the old file**; `reference_images[]`/`reference_files[]` create rows with per-collection positions starting at 1 independently; oversized/disallowed-mime rejected; `remove_media[]` deletes row + file; cross-entry media ids in `remove_media[]` rejected; entry destroy removes all files (`assertMissing`); non-owner 403.

## Done when

Uploads/replacement/removal work in the browser (`php artisan storage:link` documented as local setup), no orphan files after entry deletion, suite green, pint clean.
