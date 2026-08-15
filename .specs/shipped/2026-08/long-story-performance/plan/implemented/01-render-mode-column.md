# 01 — Render-mode column

## Scope

- Add `App\Enums\StoryOverviewMode` (backed string enum), mirroring
  `App\Enums\TableOfContentsDepth`: cases `Chapter = 'chapter'`, `Book = 'book'`;
  a `label()` ("One chapter per page" / "Entire book"); a `default()` returning
  `self::Chapter`.
- Migration: add `overview_render_mode` to `projects` — string, not null,
  `default('chapter')`.
- `Project`: add to `$fillable`, cast `'overview_render_mode' => StoryOverviewMode::class`.

Does **not**: read the column anywhere (task 03), or add UI (task 05).

## Depends on

Nothing.

## Key decisions

- Column on `projects`, not a settings model — see `00-overview.md`.
- Pre-V1 demo data: column default covers existing rows, no backfill.

## Consult

`../expanded/data-model.md`.

## Tests

- Enum `label()` / `default()`.
- A `Project` factory-made model exposes `overview_render_mode` as a
  `StoryOverviewMode` instance, defaulting to `Chapter` when unset.
