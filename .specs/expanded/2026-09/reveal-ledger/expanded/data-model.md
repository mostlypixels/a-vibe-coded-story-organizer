# Data model

## `reveals`

One table, polymorphic on the revealed fact. `Revision` is the app's only existing
`morphTo` and is the precedent to copy — including its `project_id` decision.

```
id
project_id        FK -> projects, cascade on delete
revealable_type   string
revealable_id     unsigned big int
scene_id          FK -> scenes, nullable, nullOnDelete
never_revealed    boolean, default false
note              text, nullable
timestamps

unique  (revealable_type, revealable_id)          -- criterion 7: one reveal per fact
index   (project_id, never_revealed)              -- the ledger page's own filter
index   (scene_id)
```

**`project_id` is a real column, not derived from the morph pair.** Same reason `Revision`
carries one: deleting a project cascades to its scenes and codex entries at the database
level without firing Eloquent events, so a `deleting` hook would silently never run. This
is documented in `documentation/features/revisions.md`; the same trap applies here.

## Revealable types

`CodexEntry`, `CodexAttributeValue`, `Event`. A `morphMap` in `AppServiceProvider` keeps
short stable keys in the column rather than fully-qualified class names — a rename of the
class must not orphan rows.

## Invariants

| Invariant | Enforced by |
| --- | --- |
| At most one reveal per fact | unique index + a `Reveal` model `booted()` guard for the friendly error |
| `never_revealed` and `scene_id` are exclusive | `booted()` — setting one clears the other |
| The scene belongs to the fact's project | Form Request rule, and a `booted()` assertion |
| A reveal's project matches its fact's project | `booted()` — set `project_id` from the fact, never from the request |

`never_revealed = true` with `scene_id = null` is "never told". `never_revealed = false`
with `scene_id = null` is **not** a valid saved state — it is the absence of a row. Do not
let a reveal exist in that shape; delete the row instead.

> [!WARNING]
> The exception is a **deleted scene**. `nullOnDelete` leaves exactly that forbidden shape.
> That is deliberate — see the orphan question in [open-questions.md](open-questions.md) —
> and it is why the two-state rule above is enforced on write, not read. Read code must
> tolerate the orphan.

## Story rank is never stored

Criterion 5. The reveal stores a `scene_id`; where that scene sits in the story is derived
at read time from the eight-key chain. Storing a rank would be a denormalisation that every
reorder must chase, and reorders are frequent.

## Seeding

`MelusineSeeder` gets a handful of reveals across its books — enough that the ledger page
has something to render and the "known by end of book N" cutover is visible between two
books. Without them the feature demos as an empty page.

## Migration safety

New table only; nothing existing changes. Pre-V1, so no backfill and no preservation
ceremony.
