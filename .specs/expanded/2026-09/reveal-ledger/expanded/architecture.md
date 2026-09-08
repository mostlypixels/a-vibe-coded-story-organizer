# Architecture

## Project-wide story rank

The piece that does not exist. `StoryNumbering` restarts at each book by design; this
feature needs one ordering across the whole project.

**New: `App\Support\ProjectStoryRank`** — beside `StoryNumbering`, same shape: a read-only
lookup built once per request, never stored.

```php
public static function forProject(Project $project): self;

/** 1-based rank of a scene across every book in the project, in story order. */
public function scene(Scene $scene): int;

/** True when $a comes strictly before $b. */
public function precedes(Scene $a, Scene $b): bool;

/** The rank of the last scene of $book — the "by the end of book N" cutoff. */
public function endOfBook(Book $book): int;
```

Ordering chain, every key table-qualified and every `id` tie-break present:

```
books.position, books.id,
acts.position, acts.id,
chapters.position, chapters.id,
scenes.position, scenes.id
```

The `id` tie-breaks are not optional. `position` has no unique constraint anywhere in this
app — two siblings can share one — and the codebase already documents this in
`SceneController::index` and `ChapterController::index`. A rank that drops them orders
unstably, and the ledger's answer changes between two page loads with no edit.

**One query, not a walk.** Build the rank from a single joined query over the project's
scenes ordered by the chain, numbered in PHP. Walking `book -> acts -> chapters -> scenes`
is an N+1 across a whole series.

> [!WARNING]
> `Project::sceneQuery()` and `Book::sceneQuery()` join `acts`, and `acts` carries its own
> `name` and `position`. Every ordering column must be table-qualified or the query is
> ambiguous — the same trap `Book::chapterQuery()`'s docblock describes.

## Reading and writing a reveal

- **`App\Models\Reveal`** — the `morphTo` plus the `booted()` invariants from
  [data-model.md](data-model.md).
- **`App\Services\RevealLedger`** — answers the page's two questions for a project up to a
  book: what is known, what is hidden. Takes a `ProjectStoryRank` so the comparison is done
  once for the whole page, not per row.
- **`App\Http\Controllers\RevealController`** — `store`, `update`, `destroy` on a reveal.
  Shallow-nested per convention: create under the fact, act on the reveal by id.
- **`App\Http\Controllers\RevealLedgerController@show`** — the read page, nested under the
  book.

Marking is a small action taken from the codex entry, the attribute row, or the event — not
a page of its own. It posts to `RevealController@store` and returns to where it came from.

## The leak warning

Criterion 6: flag a fact whose reveal scene comes *after* a scene that already references
it.

Mentions come from the `scene_codex_entry` pivot, maintained by `SceneReferenceMatcher`.
The warning is: the earliest referencing scene's rank is less than the reveal scene's rank.

**A mention is not a use.** The pivot records that a scene's prose contains the entry's name
or an alias — which catches "Marnborne was not there" as readily as a real disclosure. The
warning will produce false positives, and the source spec accepts that ("Rough is fine; the
writer decides").

Two consequences to design for, rather than pretend away:

- The warning must read as a question, not a verdict. Wording matters more than the query.
- It must be dismissible per fact, or a writer with one noisy entry stops reading warnings
  entirely. That needs a column — see [open-questions.md](open-questions.md).

Attribute values and events have no equivalent pivot. **The warning covers codex entries
only.** Say so in the UI rather than silently checking one type of three.

## Authorization

Every route authorizes through the owning `Project` and `ProjectPolicy`, per `CLAUDE.md`.
The fact is reached by route binding; the project is walked from the fact, never taken from
the request. `Reveal::project_id` exists for cascade safety, **not** as the authorization
path — walk the fact, so a mismatched row cannot authorize itself.

Mirror the policy check in the Form Request `authorize()`.

## Conflicts with existing invariants

- **Position ordering.** The eight-key chain above must match how the story lists order, `id`
  tie-breaks included. If they drift, the ledger and the scene list disagree about what comes
  first, with nothing failing.
- **Codex attribute values already carry story time.** An attribute value takes effect from
  an event (`start_event_id`). A reveal adds a *second* time axis to the same row: when the
  value becomes true in the story, and when the reader is told. These are genuinely
  different, and conflating them in the UI is the main way this feature could confuse.
- **Events are project-wide, scenes are per-book.** An event's reveal scene is in one book;
  the event itself belongs to the project's timeline. That is fine — but "known by end of
  book N" for an event means "told by then", not "happened by then".
