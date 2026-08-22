# 01 — Genre enum and column

## Scope

- Add `App\Enums\Genre` (string-backed): `Contemporary`, `Historical`, `Fantasy`,
  `ScienceFiction`, `Blank`. Give it `label()` and rely on `cases()`. No `routeKey()`.
- Migration: nullable string `genre` on `projects`.
- `Project`: add `genre` to `$fillable` and cast `'genre' => Genre::class`.

Not in scope: the bundle content or resolver (task 02); any UI (task 09/10).

## Depends on

- None.

## Key decisions

- Nullable: old projects and Blank-genre projects may read `null`.
- `Blank` is a real, stored case, not `null`. It means "no bundle".
- Label only — no behavior hangs off the value in v1.

## Consult

- `expanded/data-model.md` → "`projects.genre`" and "`Genre` enum".
- Mirror the shape of `app/Enums/CodexEntryType.php`.

## Tests

- Enum cast round-trips on `Project` (set `Fantasy`, reload, still `Fantasy`).
- A project can be created with `genre = null` and with `genre = Blank`.
