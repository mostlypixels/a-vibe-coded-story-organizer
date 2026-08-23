# 04 — Codex edit wiring

## Scope

- `UpdateCodexEntryRequest`: add rules for
  - `inception_event_id`, `termination_event_id`: `nullable`, `integer`, exists in `events` scoped
    to the project, **and not a bookend** (a small `RegularEvent` rule, or `whereNot` on the Start/
    End ids).
  - `new_inception_event_title`/`new_inception_event_datetime` and the termination pair: mirror the
    scene rules (`required_with` each other, `nullable`, `WithinEventWindow`).
  - **No** cross-field ordering rule — termination before inception is legal.
- `CodexEntryController::update`: use `CreatesInlineEvents` (task 03) twice to resolve inception and
  termination ids, then save the two columns with `name`/`description`. Eager-load `inceptionEvent`,
  `terminationEvent` in `edit`, and pass the picker's regular-event list (bookends excluded).

Does **not**: render the Existence card or warning (task 05), change the resolver/panel (task 06),
or touch the create form (`StoreCodexEntryRequest` stays as is).

## Depends on

01 (columns), 03 (trait).

## Key decisions

- Edit page only (`open-questions.md`; `architecture.md` → Controller).
- Bookends excluded and server-rejected; time travel saved not rejected (#7, #8).
- FK columns are plain saves, not autosaved fields (no revision snapshot for them).

## Consult

`expanded/architecture.md` → Controller, Requests. Existing `SceneController::update` for the
inline-event call shape.

## Tests

Feature, per `expanded/testing.md` → *Link write* / *Event deletion*:

- Set inception/termination to existing events → columns saved.
- Inline "+ New event" for each → event created, attached to main plotline, linked.
- Termination before inception → saved (200/redirect), not rejected.
- Event id from another project → 422; a bookend id → 422.
- Non-owner PUT → 403.
- Delete a linked event → link nulls, entry survives (may already be covered by task 01; keep the
  controller-level assurance).
