# Birth and death — overview

Link a codex entry to the event that starts its existence and the event that ends it. Then
show the entry's age at any dated moment (a scene, an event).

## Problem

Nothing today records when a character is born or an organization is founded. A reader of a
scene set in 2000 cannot see that Jane Doe (born 1980) is 20. The timeline already dates events
and resolves attribute values "as of" a moment — age is the missing derived fact.

Schema names the two links **inception** and **termination** (neutral — "created" entities
outnumber "born" ones); the UI shows the per-type wording (Born/Created, Died/Destroyed…).

## Goals

- Link each entry to an **inception** event and a **termination** event (both optional).
- Create either event on the fly from the entry edit page, with a date picker.
- Compute an entry's age as of a dated moment.
- Show age on the scene edit page's codex panel, for every lifespan-tracking type.
- Hide an entity from that panel when it does not exist at the scene's moment (before inception,
  after termination).
- Name the two events by entry type: Born/Died, Created/Destroyed, Founded/Dissolved.
- Gate the whole feature on a per-type flag (`tracksLifespan`) so a future age-less type opts out.

## Non-goals

- No new timeline mechanics. Inception/termination are ordinary events; only the *link* is new.
- No age on the public scene view, story overview, or export — edit pages only, this pass.
- No lifespan validation against attribute periods (a value anchored after termination is allowed).

## User stories

- As a writer I set Jane's inception to a 1980 event, so every later scene shows her age.
- As a writer I click "+ New event" beside the field and pick a date, without leaving the entry
  page.
- As a writer editing a 2000 scene I see "Jane Doe — Age 20" in the codex panel.
- As a writer editing a 1970 scene I do not see Jane at all — she is not born yet.
- As a writer I mark an organization *founded* in 1900 and *dissolved* in 1950.

## Acceptance criteria

- An entity that exists at the scene's moment shows a whole-year age (lifespan-tracking types).
- An entity not yet born, or already gone, at the moment is absent from the panel.
- A scene with no event shows no codex values (existing "assign an event" copy).
- Termination before inception is allowed (time travel): age is suppressed, the entity still shows
  in every scene, and the edit page warns to track age via an attribute.
- Deleting the linked event clears the link; the entry survives.
- A non-owner saving a link gets 403.
- Field labels and card visibility follow the entry type.
