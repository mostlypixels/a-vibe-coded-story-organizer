# 03 — Genre seed action

## Scope

- A service in `app/Services` with one entry point:
  `seed(User $user, Genre $genre, string $name): Project`.
- Steps, in one DB transaction:
  1. Create the project (`$user->projects()->create([...])`) with `name` and `genre`. The
     `created` hook makes the first book, main plotline, and Start/End bookends.
  2. Apply the bundle for `$genre`: create attributes (positions set), tags, sample entries.
  3. Set each sample entry's attribute values through `AttributeTimeline` at the Start event
     (`ensureBaseline` / `upsertAt`).
  4. Build the act/chapter skeleton on `$project->books()->first()`.
- `Blank` creates the project and applies nothing.

Not in scope: the command (04), the web flow (09), demo install (06).

## Depends on

- 01, 02.

## Key decisions

- **Events on.** Never call this inside `WithoutModelEvents` — it relies on the `created`
  hook. Do not create a second book.
- Attribute values only through `AttributeTimeline` (leading-anchor invariant).
- v1 bundles seed no scenes, so no `SceneReferenceMatcher` call is needed yet. If a bundle
  ever adds scenes, run the matcher last — leave a short note, not the call.

## Consult

- `expanded/architecture.md` → "Shared seed action".
- `expanded/data-model.md` → "Seeding rules", "What already exists".
- `documentation/features/codex.md` → temporal attributes, seeder requirements.

## Tests

- Fantasy: project has `genre = Fantasy`, one book (not two), expected attributes on the
  right types, tags, sample entries.
- Every seeded attribute value resolves at Start (`valueAt(startEvent)` non-null).
- Blank: project created, no attributes/tags/extra entries, but book + plotline + bookends
  present.
- A forced failure mid-seed rolls back — no project row remains.
