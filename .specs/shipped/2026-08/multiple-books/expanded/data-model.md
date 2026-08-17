# Data model

```
User
 └── Project
      ├── Book          (>= 1, ordered by position)   NEW
      │    ├── Act → Chapter → Scene
      │    └── PublicationSetting  (moved off Project)
      ├── Plotline / Event         (shared across books)
      ├── CodexEntry / Tag / CodexAttribute (shared)
      └── WordCountSnapshot        (project-wide total)
```

## `books` (new)

| Column | Type | Notes |
|---|---|---|
| `project_id` | FK cascade | ordering scope; `HasSiblingPosition` |
| `name` | string, **nullable** | `null` = "no name of my own"; see *The name fallback* |
| `description` | text, nullable | rich HTML, `SanitizesRichHtml` — see note below |
| `position` | int | auto-assigned in `creating()`, per project |
| `cover_image` | string, nullable | the **EPUB** cover, `CoverImageService::BOOK_COVER_DIRECTORY` |
| `language` | string(10), default `'en'` | moved from `projects` |
| `author`, `publisher`, `isbn` | string, nullable | moved from `projects` |
| `rights` | text, nullable | moved from `projects` |
| `dedication`, `acknowledgements`, `preface`, `postface` | text, nullable | moved; raw Markdown |
| `overview_render_mode` | string, default `'chapter'` | moved; the overview is per book |

`description` is **not** in the source spec. A book needs a blurb the way a project does, and
`book.json` needs somewhere to put it; drop the column if that is unwanted — nothing else
depends on it.

`projects` keeps `name`, `description`, `cover_image` (the dashboard card image — a different
image from the book cover), `daily_word_goal`, `total_word_goal`, and gains one column:

| Column | Type | Notes |
|---|---|---|
| `last_book_id` | FK books, nullable, `nullOnDelete` | the book you were last in, per project |

`last_book_id` is **not fillable** — only the tracking middleware writes it, the same rule
`users.active_project_id` follows. It needs its own migration **after** `books` exists: the two
tables reference each other.

## The name fallback

`books.name` is nullable, and that is the whole mechanism that stops a one-book project holding
two names that drift apart.

- `null` → `Book::displayName()` returns `$this->project->name`. The book tracks every project
  rename.
- a string → `displayName()` returns it. A project rename never reaches it.
- `Book::hasOwnName()` (`name !== null`) is the **single predicate** gating how visible the book
  layer is — see [`ui.md`](ui.md).

> [!IMPORTANT]
> **Every display site calls `displayName()`, never `->name`.** Picker, breadcrumb, page title,
> book index, `books.show`, search rows, both exporters, the EPUB title page and `dc:title`.
> Reading `->name` renders an empty string for the common case.

Two rules keep it honest:

- **A `Book::created` hook copies the project's current name onto every unnamed sibling.** When
  the second book arrives the project stops being a one-book project, so the first book needs
  its own identity — otherwise a later rename to a *series* name retitles the first volume.
  Suppressed under `WithoutModelEvents`, which is correct: seeders name their books, and an
  archive replays names verbatim.
- **The name is optional on the sole book, required from the second onward**
  (`Rule::requiredIf` on whether the project already has a book). Optional so the writer can
  edit the sole book's ISBN without a forced rename; required so two books never show the same
  label. The importer must preserve a `null` name — coercing it to the project name
  materializes the value and breaks the tracking.

## Changed foreign keys

| Table | Was | Becomes |
|---|---|---|
| `acts` | `project_id` | `book_id` (cascade) |
| `publication_settings` | `project_id` unique | `book_id` unique |

Nothing else moves. `plotlines`, `events`, `codex_*`, `tags`, `revisions.project_id`,
`word_count_snapshots.project_id`, `imports` all stay project-scoped.

> [!WARNING]
> Do **not** keep `acts.project_id` alongside `book_id` "for convenience". Two paths to the
> same owner drift the first time an act moves between books, and the walk
> `$act->book->project` is the one every controller already mirrors for revisions
> (`revisionProject()`).

## Model invariants

- **Every project has at least one book.** `Project::booted()`'s `created` hook creates it
  alongside the main plotline and the Start/End bookends, with **no name** — it tracks the
  project's until someone names it.
- **The last book cannot be deleted** — `abort_if($book->project->books()->count() === 1, 403)`
  in `BookController@destroy`, the same guard style as `is_main` / `is_fixed`. This is what
  keeps "the project's books" total everywhere else.
- **`Book::siblingScopeColumn()` is `project_id`**; **`Act::siblingScopeColumn()` becomes
  `book_id`**, and `Act::booted()`'s `creating` hook scopes `max(position)` to `book_id`.
- **`Book::deleting` must purge covers the FK cascade would strand.** `book → acts → chapters`
  cascades at the DB level and fires neither `Act::deleting` nor `Chapter::deleting`, so the
  hook deletes the book's own `cover_image` and every surviving chapter cover under it —
  exactly the leak `Project::deleting` already closes one level up.
- **`Book::deleted` records a word-count snapshot** (`WordCountSnapshotRecorder::record($book->project)`),
  for the same reason `Act::deleted` and `Chapter::deleted` do: the scenes vanished by cascade,
  firing no `Scene::deleted`.
- `Book` uses `HasRevisions` + `HasSiblingPosition` + `SanitizesRichHtml`;
  `revisionProject()` returns `$this->project`.

> [!WARNING]
> **Seeding caveat, one level deeper.** `DatabaseSeeder` runs `WithoutModelEvents`, so the
> auto-created book never appears — every seeder must create its book explicitly and set
> `position`, exactly as it already does for the main plotline and act/chapter/scene positions.

## Autosave registry

`AutosavableFields::REGISTRY` gains a `'book'` slug and loses five fields from `'project'`:

```php
'project' => [Project::class, ['description' => FieldKind::Rich]],
'book'    => [Book::class, [
    'description'      => FieldKind::Rich,
    'dedication'       => FieldKind::Markdown,
    'acknowledgements' => FieldKind::Markdown,
    'preface'          => FieldKind::Markdown,
    'postface'         => FieldKind::Markdown,
    'rights'           => FieldKind::Plain,
]],
```

Re-key the matching `config/revisions.php` `caps`/`windows` entries (`project.dedication` →
`book.dedication`) and add `book` to `LongTextColumnsMigrationTest`'s own copy of the widened
column list.

## Migrations

Pre-V1 rule applies: the only data is the seed, so these are **destructive, no backfill** —
reseed with `php artisan migrate:fresh --seed`. `down()` still reverses the schema honestly.

1. `create_books_table`.
2. `add_last_book_id_to_projects_table` — separate from 1, because `books.project_id` already
   points the other way.
3. `add_book_id_to_acts_table` — add `book_id` (NOT NULL, cascade), drop `project_id`.
4. `move_publication_settings_to_books` — `book_id` unique, drop `project_id`.
5. `drop_book_metadata_from_projects` — drop the twelve moved columns.
6. `drop_moved_project_revisions` — delete `revisions` rows where
   `revisionable_type = App\Models\Project` and `field` is one of the five moved fields. They
   point at columns that no longer exist; re-pointing them at the seeded book buys nothing
   pre-V1.
7. `rename_story_overview_mode_book_value` — rewrite `'book'` → `'whole'`
   (see [`architecture.md`](architecture.md#the-book-naming-collision)).

## Factories & seeders

- New `BookFactory` (`project_id` => `Project::factory()`, `name`, `position` 1).
- `ActFactory` swaps `project_id` for `book_id` => `Book::factory()`. **This is what breaks the
  73 test files that call `Project::factory()` with an act tree** — see
  [`testing.md`](testing.md).
- `MelusineSeederEn/Fr/It`, `LongNovelSeeder`, `LoremIpsumSeeder`: create the book explicitly
  (positions set by hand), hang acts off it, and move the epub metadata onto it. Seed **one**
  project with **two** books so the feature has demo data — the natural candidate is
  `LoremIpsumSeeder`, which exists to exercise shapes rather than to read well.
