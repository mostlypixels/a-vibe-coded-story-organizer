# Birth and death — testing

Plain PHPUnit, `RefreshDatabase`, factories, `actingAs()`, named routes.

## Age & existence (unit, `App\Support\Age` + `CodexEntry`)

- Inception 1980, moment 2000 → years = 20.
- Inception 1980-06, moment 2000-01 → 19 (floor, not yet had birthday).
- No inception event → `ageAt` returns null.
- Null moment → null.
- Inverted lifespan (termination 1980, inception 2000) → `hasInvertedLifespan()` true, `ageAt`
  returns null for any moment.
- `existsAt`: before inception → false; at inception (inclusive) → true; between → true; at
  termination (inclusive) → true; after termination → false.
- `existsAt`: no links → true always; only inception set → true from inception on; only termination
  set → true up to termination; inverted lifespan → true always; non-tracking type → true always.

## Link write (`CodexEntryController`)

- Set inception to an existing event → column saved.
- "+ New event" inception (title + datetime) → event created, attached to main plotline, linked.
- Same for termination.
- Termination before inception → **saved, not rejected** (time travel is allowed).
- inception_event_id pointing at another project's event → 422 (project scope).
- inception_event_id = a bookend id → 422 (bookends excluded).
- Non-owner PUT → 403 (mirror the existing codex authorization test).

## Event deletion

- Delete a linked inception event → entry row survives, `inception_event_id` is null (nullOnDelete).

## Scene edit panel (feature)

- Character with inception 1980, scene assigned to a 2000 event → response shows "Age 20".
- Scene dated before the inception → the entity is **absent** from the panel.
- Scene dated after the termination → the entity is **absent**.
- Entry existing at the moment with an inception but zero attribute values → still appears, with
  its age (guards the resolver keep-if-age filter — the regression the change exists for).
- Inverted lifespan → entity shows in every scene, no age line.
- Unassigned scene → existing "assign an event" copy, nothing resolved.

## Inverted-lifespan warning (edit page)

- Termination before inception → the codex edit page shows the warning under the termination field.
- Normal order → no warning.

## Per-type gating

- `CodexEntryType::inceptionLabel()`/`terminationLabel()` return the right word per case.
- `tracksLifespan()` is true for all three current cases.
- A codex edit page for a lifespan type renders the Existence card; the card's absence for a
  non-tracking type is not testable until such a type exists (note, no test now).

## Bug-fix guard

The resolver keep-if-age change needs a test that fails before it: an existing entry with an
inception event and no attributes must be present in `resolve()`'s output.
