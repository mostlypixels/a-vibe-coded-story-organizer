# Birth and death — architecture

## Age computation

New value object `App\Support\Age`:

```
Age::between(CarbonInterface $inception, CarbonInterface $moment): self
->years: int   // whole years, floor
```

- Whole years via Carbon `diffInYears` (floor). Precision beyond years is a possible later add —
  keep the object the single home so the format lives in one place.
- No "before inception" state: the panel hides an entity that does not exist yet (see the resolver
  below), so `ageAt` is only ever asked for a moment inside the entity's existence window.

`CodexEntry::ageAt(?Event $moment)` delegates: returns `null` when no inception event, no moment,
or `hasInvertedLifespan()` (termination before inception — a time traveller); else
`Age::between($this->inceptionEvent->event_datetime, $moment->event_datetime)`.

An inverted lifespan disables age everywhere — the timeline is nonsensical for a single number, so
the author tracks age by hand through attributes instead. The edit page says so (see `ui.md`).

`CodexEntry::existsAt(?Event $moment): bool` — the existence-window test the resolver filters on.
True when `$moment` is null (unassigned scene shows nothing anyway), when the type does not track a
lifespan, when the lifespan is inverted (always shown), or when
`inception <= moment <= termination` (each bound inclusive, an unset bound is open). One home for
the rule so the resolver and any later caller agree.

## Inline event creation — extract the scene pattern

`SceneController::resolveHappensDuringEvent()` already creates an event on the fly from
`new_event_title` / `new_event_datetime` and attaches it to the main plotline. Inception and
termination are a **second and third caller** — extract it.

- New trait `App\Http\Controllers\Concerns\CreatesInlineEvents` with
  `resolveInlineEvent(Project $project, ?string $title, ?string $datetime, ?int $existingId): ?int`.
- `SceneController` uses it (drops its private method).
- `CodexEntryController::update`/`store` call it twice — once per inception/termination field pair.

## Controller

Keep the link on the existing codex edit flow — no new controller.

- `CodexEntryController::edit` already loads events and `startEvent`. Also eager-load
  `inceptionEvent`, `terminationEvent`; pass the same regular-event list the pickers need (exclude
  bookends).
- `CodexEntryController::update`: resolve inception and termination event ids via
  `CreatesInlineEvents`, then set the two columns alongside `name`/`description`.
- Reuse the existing revision snapshot path only for autosaved fields; the two FK columns are
  plain saves (not autosaved).
- **Edit page only.** The create form (`codex.create`) gets no lifespan fields — the writer sets
  them after the entry exists. `StoreCodexEntryRequest` is untouched.

## Requests

Extend `UpdateCodexEntryRequest`:

- `inception_event_id`, `termination_event_id`: `nullable`, `integer`, `Rule::exists('events','id')`
  scoped to the project, and **not a bookend** (a custom `RegularEvent` rule, or `whereNot` on the
  fixed ids).
- `new_inception_event_title` / `new_inception_event_datetime` and the termination pair: mirror the
  scene rules (`required_with` each other, `WithinEventWindow`, `nullable`).
- **No cross-field ordering rule.** Termination before inception is a legal state (time travel);
  the model's `hasInvertedLifespan()` handles the fallout, not validation.

## As-of resolver — existence window + age

`CodexAsOfResolver::resolve` groups entries by type with their attribute values as of `$moment`.
Two changes:

1. **Existence filter.** Drop any entry where `entry->existsAt($moment)` is false — the panel shows
   an entity only while it exists. Before inception and after termination it is hidden; an entity
   with no lifespan links, or an inverted one, always passes.
2. **Age.** Add an `age` key (`?Age`) to each surviving entry row, from `entry->ageAt($moment)`.

> [!WARNING]
> The resolver today drops any entry whose attribute list is empty. An existing entity with an age
> but no attribute values must still show. Change the filter to keep a row when it has attributes
> **or** a non-null age.

Eager-load `inceptionEvent` and `terminationEvent` on the entries query (both feed `existsAt`) to
avoid N+1 across the panel.

## Authorization

Unchanged pattern: every write authorizes through `codexEntry->project` via `ProjectPolicy`; the
Form Request `authorize()` mirrors it. No new policy.
