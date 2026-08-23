# Birth and death — plan overview

Link each codex entry to an **inception** event and a **termination** event. Compute age. Show it
on the scene/event codex panel, and hide entities that do not exist at the moment.

Read `expanded/*.md` for detail. This file is the manual: order, binding defaults, invariants.

## Execution order

| # | Task | Purpose |
|---|---|---|
| 01 | schema-and-relations | Migration + `CodexEntry` FK columns, relations, fillable. |
| 02 | domain-computations | Enum labels + `tracksLifespan`; `Age` object; `ageAt`/`existsAt`/`hasInvertedLifespan`; unit tests. |
| 03 | inline-events-trait | Extract `CreatesInlineEvents`; refactor `SceneController` onto it (scene tests stay green). |
| 04 | codex-edit-wiring | `UpdateCodexEntryRequest` + `CodexEntryController::update`: save links, inline events, bookend-excluded validation, eager-load. |
| 05 | codex-edit-ui | Existence card (gated by `tracksLifespan`), shared event-picker partial, inverted-lifespan warning. |
| 06 | panel-existence-and-age | As-of resolver existence filter + age; panel rendering; demo seed. |

Order is dependency order for `ship-plan`: 01 → 02 → 03 → 04 → 05 → 06.

## Binding defaults (do not re-litigate — see `open-questions.md`)

- **Schema is neutral:** `inception_event_id`, `termination_event_id` FKs, `nullOnDelete`. Not
  timeline attributes.
- **Per-type gate:** `CodexEntryType::tracksLifespan()` (true for all three now) gates the edit
  fields, the age, and the existence filter.
- **Labels:** Character Born/Died, Location Created/Destroyed, Organization Founded/Dissolved.
- **Existence window (panel):** show an entity only when `inception <= moment <= termination`,
  each bound inclusive, an unset bound open. Before inception and after termination → hidden. No
  "not yet" label, no "gone" tag.
- **Time travel allowed:** termination before inception is a legal save. `hasInvertedLifespan()`
  then suppresses age and **skips** the existence filter (entity always shows); the edit page warns.
- **Age:** whole years, `App\Support\Age` the single home. `ageAt` null when no inception / no
  moment / inverted.
- **Edit page only:** the create form gets no lifespan fields.

## Invariants every task preserves

- **Authorization through the project.** Every new write authorizes via `ProjectPolicy`, mirrored
  in the Form Request `authorize()`. Non-owner → 403. (See `CLAUDE.md`.)
- **Bookends stay the bookends.** Inception/termination pickers exclude Start/End; the server
  rejects a bookend id. Regular-event datetimes still obey `WithinEventWindow`.
- **No orphan links.** A deleted event nulls the link (`nullOnDelete`); the entry survives.
- **Existence + age live on the model, not in Blade or the controller.** `existsAt` and `ageAt` are
  the single homes; the resolver and views only call them.
- **Timeline mechanics unchanged.** Inception/termination are ordinary events; attribute periods,
  `AttributeTimeline`, and `Project::startEvent()/endEvent()` are not touched.
