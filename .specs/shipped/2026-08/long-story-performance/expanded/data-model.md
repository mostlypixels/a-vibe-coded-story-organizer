# Data model

## Enum — `App\Enums\StoryOverviewMode`

Mirror `TableOfContentsDepth` (backed string enum + `label()`).

```php
enum StoryOverviewMode: string
{
    case Chapter = 'chapter';   // default — one chapter per page
    case Book = 'book';         // whole story on one page
}
```

`label()` → "One chapter per page" / "Entire book". Add a `default()` returning
`self::Chapter` so the column default and any lazy path read one source.

## Storage — column on `projects`

A single view preference is a project attribute, not an aggregate, so it lives
on `projects`, not a new settings model (see open-questions for the rejected
`StorySetting` alternative).

- Migration: add `overview_render_mode` string, `default('chapter')`, not null.
- `Project`: add to `$fillable`, cast `'overview_render_mode' => StoryOverviewMode::class`.
- Pre-V1 demo data — no backfill ceremony; the column default covers existing rows.

## No change to the tree

`Act` / `Chapter` / `Scene` and their `position` invariant are untouched.
`Scene::renderedContents` is unchanged (caching deferred).
