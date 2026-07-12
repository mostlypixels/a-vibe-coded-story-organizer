# 02 — Project metadata UI

## Scope

- `resources/views/projects/edit.blade.php` gains a "Book metadata" section inside the
  existing single edit `<form>`: `language`, `author`, `publisher`, `rights`, `isbn` as
  standard inputs, plus `cover_image` as a file input copied from the existing Codex cover
  pattern (`resources/views/codex/partials/fields.blade.php` — preview `<img>`, "remove"
  checkbox, Tailwind `file:` classes, `<x-input-error>`). The form tag needs
  `enctype="multipart/form-data"` added (it currently has none, since it has no file field
  today).
- `app/Http/Requests/UpdateProjectRequest.php` gains rules for all six fields:
  `language` (`required|string|max:10`), `author`/`publisher` (`nullable|string|max:255`),
  `rights` (`nullable|string|max:1000`), `isbn` (`nullable|string`, `new ValidIsbn` from
  task 01), `cover_image` (`App\Support\CodexMediaRules::coverRules()`, reused directly — do
  not duplicate the mime/size list).
- `app/Http/Controllers/ProjectController.php::update()` gains cover-image handling as
  **private methods on the controller** (per the grilled decision — no new service class):
  store a new upload, delete the old file when replaced or explicitly removed (a
  `remove_cover_image` checkbox, mirroring Codex's `remove_media[]` pattern but for the
  single column), following `CodexMediaService::store()`'s store-then-unlink-on-failure
  pattern for the actual file write.
- Extend `Project`'s `deleting` hook in `app/Models/Project.php::booted()` (currently only
  purges Codex media) to also delete `cover_image` off the `public` disk when set, so project
  deletion doesn't leak an orphan cover file — this is a new orphan-file class this task
  introduces, so it must close it in the same task.

## Explicitly not in scope

- Anything about how the epub generator reads/uses these fields (task 04).
- The new "Epub export" section itself on the admin export page (task 07) — this task only
  touches the Project edit screen.

## Depends on

01 (needs the six columns and `ValidIsbn` to exist).

## Key decisions already made

- Cover image validation reuses `CodexMediaRules::coverRules()` verbatim — same allowed
  types/size as Codex covers, not a new constant set.
- Storage disk is `public`, matching `CodexMediaService::DISK` — no new disk config.
- No `ProjectCoverService` — inline private `ProjectController` methods (CLAUDE.md: no
  abstraction before a second caller).

## Docs to consult

- `expanded/ui.md` — the Project edit form section layout.
- `expanded/data-model.md` — the deleting-hook orphan-file note.
- `app/Services/CodexMediaService.php` — the store/delete pattern to mirror (not reuse
  directly — Codex's service is entry-media-table-shaped; this is a single path column).
- `resources/views/codex/partials/fields.blade.php` — the cover-upload Blade pattern to copy.

## Tests

Extend `tests/Feature/ProjectTest.php`:
- Happy path: owner updates a project with all six new fields (including a cover upload);
  response redirects successfully and the project's attributes/`cover_image` path persist.
- Authorization: non-owner update attempt still 403s (existing coverage, just confirm it
  still passes with the larger request payload).
- Validation failures: invalid `isbn` (bad checksum) and an invalid `cover_image` (wrong
  mime type, oversized) each produce `assertSessionHasErrors()`.
- Cover replace: uploading a new cover when one already exists deletes the old file off the
  `public` disk (`Storage::disk('public')->assertMissing($oldPath)`) and stores the new one.
- Cover removal: submitting the remove checkbox with no new upload clears `cover_image` and
  deletes the file.
- Project deletion: a project with a `cover_image` set has that file removed from the `public`
  disk when the project is deleted (extends whatever test already covers the Codex-media
  purge on project deletion).
