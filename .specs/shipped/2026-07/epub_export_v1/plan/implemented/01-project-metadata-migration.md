# 01 — Project metadata migration

## Scope

- New migration adding six columns to `projects`: `language` (string, `varchar(10)`, **not
  null, default `'en'`**), `author` (nullable string), `publisher` (nullable string), `rights`
  (nullable string/text), `isbn` (nullable string, e.g. `varchar(17)`), `cover_image`
  (nullable string — storage path, not the file itself).
- Add all six to `Project::$fillable` in `app/Models/Project.php`.
- New `app/Rules/ValidIsbn.php`: a single-purpose `Illuminate\Contracts\Validation\ValidationRule`
  class (same shape as `app/Rules/ValidMarkdown.php`) that strips hyphens/spaces from the input,
  checks it's exactly 13 digits, and validates the ISBN-13 checksum. Fails with a clear message
  when malformed; does **not** run at all when the value is empty (pair with `nullable` in the
  consuming Form Request — this task only adds the rule class itself, not its Form Request wiring).

## Explicitly not in scope

- Editing/uploading any of these fields from the UI — that's task 02.
- Anything about the epub generator reading these fields — that's task 04.

## Depends on

Nothing (first task).

## Key decisions already made

- `language` is **required with a DB-level default** (`'en'`), not nullable — every existing
  project gets a valid value with no backfill script needed (`data-model.md`).
- `isbn` is stored **as typed** (hyphens or not) — `ValidIsbn` strips punctuation only to check
  the checksum, it does not normalize the stored value.
- `cover_image` is a **plain path column**, not a new `CodexMedia`-style table — see
  `data-model.md` for the rationale (single image, no position/collection bookkeeping needed).

## Docs to consult

- `expanded/data-model.md` — full column table and rationale.
- `app/Rules/ValidMarkdown.php` — the exact style/shape to match for `ValidIsbn`.

## Tests

- A migration test isn't idiomatic here; instead, cover this via `ProjectTest`: a factory-built
  `Project` (or one created via `Project::create()`) has `language` default to `'en'` when not
  set explicitly, and all six new attributes are mass-assignable.
- `ValidIsbn` gets its own unit test (`tests/Unit/Rules/ValidIsbnTest.php` or similar, matching
  wherever `ValidMarkdown`'s test — if one exists — lives): valid ISBN-13 with and without
  hyphens passes; wrong length, non-numeric characters, and a bad checksum digit each fail.
