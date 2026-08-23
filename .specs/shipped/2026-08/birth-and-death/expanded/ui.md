# Birth and death — UI

## Enum labels

Add to `App\Enums\CodexEntryType` (the schema is neutral — `inception`/`termination`; these labels
carry the per-type wording):

- `inceptionLabel(): string` — Character "Born", Location "Created", Organization "Founded".
- `terminationLabel(): string` — Character "Died", Location "Destroyed", Organization "Dissolved".
- `tracksLifespan(): bool` — true for all three current types. Gates the whole feature per type
  (see below), so a future type (Object, Concept…) opts out by returning false.

Keep them beside the existing `label()` / `pluralLabel()` match blocks. No "not yet" label — an
entity that does not exist yet is hidden, not labelled.

## Codex entry edit page

New card **"Existence"** on `resources/views/codex/edit.blade.php`, above or beside the attribute
timeline. **Rendered only when `$type->tracksLifespan()`.** Two field groups — inception and
termination — each a copy of the scene edit page's "Happens during" block
(`resources/views/scenes/edit.blade.php`):

- A `<x-select>` of the project's regular events (bookends excluded) + a "— Not set —" option.
- A "+ New event" toggle (Alpine) revealing `new_*_title` and `new_*_datetime` (datetime-local
  with `min`/`max` = window bounds).
- Field labels from `$type->inceptionLabel()` / `$type->terminationLabel()`.

### Inverted-lifespan warning

When `$entry->hasInvertedLifespan()` (termination before inception — a time traveller), show a
small muted note **under the termination field**:

> Termination is before inception, so age is not calculated. Track age with an attribute instead.

Server-rendered from the saved state (`hasInvertedLifespan()`), not live Alpine — the datetimes may
be inline-new and unsaved, so a live check would be more code for a rare edge. It updates on the
next save, which is enough.

Reuse, don't reinvent: the scene block's markup, the `windowMin`/`windowMax` hints, and the
`+ New event` Alpine toggle already exist — lift them into a shared partial
(`codex/partials/event-picker.blade.php` or `components/event-picker`) if the copy is more than
trivial, so scene and codex stay in step.

## Scene edit page — age in the as-of panel

`resources/views/codex/partials/as-of.blade.php` renders each entry row. The resolver has already
**hidden entities that do not exist at the moment** (before inception, after termination), so the
partial only draws entities that are present. Add an age line when the row's `age` is non-null:

- `Age {{ $row['age']->years }}`.
- No age line when `age` is null (no inception event, or an inverted lifespan).

Age reads for every lifespan-tracking type, not only characters (a location has an age since it was
created). The event edit page uses the same partial and resolver, so age there is free.

## Empty / edge states

- Entity not yet born / already gone at the moment: absent from the panel (resolver filter).
- Entity with an inception but no attributes, existing at the moment: shown, with its age.
- Scene unassigned: the panel already shows "Assign an event…"; no age.
