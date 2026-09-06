# Architecture

## Routes

Add `'show'` to the `->only([...])` list of five existing shallow resources in
`routes/web.php`:

| Resource | Line | Resulting route |
|---|---|---|
| `projects.plotlines` | 129 | `GET /plotlines/{plotline}` → `plotlines.show` |
| `projects.events` | 138 | `GET /events/{event}` → `events.show` |
| `books.acts` | 168 | `GET /acts/{act}` → `acts.show` |
| `books.chapters` | 175 | `GET /chapters/{chapter}` → `chapters.show` |
| `books.scenes` | 181 | `GET /scenes/{scene}` → `scenes.show` |

Shallow nesting puts each `show` on the member URL, so no route collides with the
`/acts/{act}/move-up` style member routes declared after them.

## Controllers

One `show()` per controller, beside the existing `edit()`. Shape follows
`CodexEntryController::show()` (line 101):

- `$this->authorize('view', $project)` — **`view`, not `update`**. Reading is not editing.
  Walk to the project the same way `edit()` does (`$scene->chapter->act->book->project`).
- Eager-load the relations the view lists. Every child list is a potential N+1.
- Return the view. No `EventWindow`, no destination lists, no `DuplicateName` — those are
  edit-form concerns.

Loads per action:

- `plotlines.show` — `events` ordered by `event_datetime`.
- `acts.show` — `chapters.scenes`, `withCount('scenes')`, `StoryNumbering::forBook()`.
- `chapters.show` — `scenes`, `act`, `withSum('scenes as word_count', 'word_count')`,
  `StoryNumbering::forBook()`.
- `scenes.show` — `chapter.act.book`, `event`, `mentionedEvents`,
  `codexReferences.cover` ordered by `(type, name)` (copy the `edit()` query),
  `StoryNumbering::forBook()`.
- `events.show` — `plotlines`, `scenes.chapter.act`, `mentioningScenes.chapter.act`, and
  the codex entries this event begins or ends.

## New service

`app/Services/EventLifespanEntries.php` — the codex entries whose `inception_event_id` or
`termination_event_id` is this event. There is no relation for it today: `CodexEntry`
declares `inceptionEvent()`/`terminationEvent()`, and nothing walks back. One query,
returning the entries grouped by role.

```php
/** @return array{inceptions: Collection<int, CodexEntry>, terminations: Collection<int, CodexEntry>} */
public function forEvent(Event $event): array
```

Nothing else needs extracting. The other four `show` actions are a load plus a view, and
their `edit()` actions do not share the read assembly the way `CodexEntryController` did —
do not invent a service to make them symmetric.

## Link repointing

| File | Change |
|---|---|
| `resources/views/{plotlines,acts,chapters,scenes,events}/index.blade.php` | row name → `*.show`; add `x-icon-view-link` |
| `app/Enums/SearchDomain.php` `viewRoute()` | full map, one `show` route per case; the "falls through to editRoute" docblock goes |
| `resources/views/codex/show.blade.php:120` | referencing scene → `scenes.show` |
| `resources/views/components/story-chapter.blade.php:48` | add a view link beside the edit icon |
| `resources/views/codex/partials/as-of.blade.php` | unchanged — already points at `codex.show` |

`app/Services/RecentlyEdited.php` keeps its `*.edit` URLs; see `open-questions.md`.

## Untouched

- `update()` still redirects to `*.edit` via `redirectAfterSave()`. Saving lands back in
  the form.
- `destroy()` still redirects to the index.
- The `x-icon-view-link` component exists (`resources/views/components/`) and needs no
  change; it is used once today, in `components/search/result-row.blade.php`.
