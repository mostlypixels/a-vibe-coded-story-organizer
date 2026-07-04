# Codex — Open questions & decisions to confirm

Ambiguities in the source spec and design forks where a project convention doesn't dictate the answer. Recommended default is listed first; confirm before implementing.

## 1. One table+enum vs three tables (entity storage)
The spec says "could possibly be a shared base class."
- **Recommended:** single `codex_entries` table with a `type` enum + one `CodexEntry` model. Columns are identical across types; the type-specific data *is* the flexible attribute system, so a shared table is the DRY, KISS choice and matches "shared base class."
- **Alternative:** three tables/models sharing a `HasCodexProfile` trait (guidelines favor composition/traits over inheritance). More boilerplate, better if the types diverge structurally later.
- **Also:** if child models (`Character`/`Location`/`Organization`) are wanted for readability, use single-table inheritance via a global `type` scope on top of the single table. Confirm whether distinct model classes are required.

## 2. One controller with `{type}` vs three controllers
- **Recommended:** one `CodexEntryController` with a `{type}` route segment (DRY; flows identical).
- **Alternative:** `CharacterController`/`LocationController`/`OrganizationController`, matching the existing "one controller per entity" pattern (Act/Chapter/Scene). More files, more duplication, but each nav item maps to its own resource route and it's the most literal reading of "three menu entries." Confirm preference.

## 3. Tags vs "tags / categories" as two dimensions
The spec bullets "tags / categories" together and the middle column is titled "tags and categories."
- **Recommended:** one flat `tags` taxonomy for v1.
- **Open:** are *categories* a separate axis (e.g. a single-select "kind": ally/enemy/neutral for characters; city/building/region for locations)? If so, add a `category` column or a second taxonomy. Needs product input.

## 4. Attribute value type — text only vs typed
Spec examples are all text (blonde, green, red).
- **Recommended v1:** plain `value` text column.
- **Open:** should attributes declare a data type (select/enum with preset options, number, boolean, color reusing `x-color-picker`)? This changes `codex_attributes` (add `data_type` + options) and the value input. Deferred as a non-goal but likely wanted soon — confirm scope.

## 5. `applies_to` as JSON vs pivot
- **Recommended:** JSON array column on `codex_attributes` (three fixed types; filter in PHP).
- **Alternative:** `codex_attribute_type` pivot if you need to query "attributes applicable to characters" in SQL or want referential integrity on the type set. Confirm expected attribute volume.

## 6. Step-function (start-only) vs start+end periods
- **Recommended:** start-anchored step function (no stored end) — gap-free and overlap-free by construction, and safe under event deletion (see [`attribute-timeline.md`](attribute-timeline.md)).
- **Alternative:** explicit `start_event_id` + `end_event_id` per period. More intuitive to some users but invites holes/overlaps and duplicated maintenance; rejected unless a period must be able to *not* extend to the next change. Confirm the step-function semantics match the writer's mental model.

## 7. What does "value at an event" mean when a change happens *at* that exact event?
Spec: "dyes their hair during event Halloween and their hair is green from that point on."
- **Recommended:** the new value takes effect **at and after** the anchor event (inclusive) — `valueAt(Halloween) = green`. This matches "from that point on."
- **Decided (spec'd in [`attribute-timeline.md`](attribute-timeline.md)):** since `event_datetime` isn't unique, anchors order canonically by `(event_datetime, events.id)`, and `valueAt(Event)` gives an anchor-identity match priority over the datetime lookup — so "during event X" always sees X's own value even when another event shares X's datetime.
- Confirm there's no need for "before/during/after" granularity within a single event.

## 8. Media: single save vs per-item AJAX
- **Recommended:** everything in the single entry `<form>`; per-item removals via hidden `remove_media[]` inputs processed on save (one atomic Save).
- **Alternative:** dedicated media upload/delete routes (AJAX), closer to the move-up/down `wantsJson()` pattern. More responsive, more surface area. Confirm.

## 9. Description: Markdown or plain?
Scene `contents`/`notes` use `ValidMarkdown` + `Str::markdown()` render. Should codex `description` be Markdown too (consistent) or plain text? **Recommended:** Markdown, reusing `ValidMarkdown` and the Story overview's render path.

## 10. Storage disk & public exposure
Media uses the `public` disk (needs `php artisan storage:link`). Confirm images should be publicly reachable by URL (anyone with the link) vs. streamed through an authorized controller route. **Recommended for v1:** `public` disk + `storage:link`; note it's not access-controlled. If entries are sensitive, a `codex.media.show` route authorizing via project is the safer path.

## 11. Attribute sheet on create
Periods need an entry id, so the full timeline editor only works on **edit**. **Recommended:** on create, capture just the Start-baseline value per applicable attribute; add other periods after saving. Confirm that two-step flow is acceptable UX.

## 12. Non-goal confirmations
Confirm these are genuinely out of scope for v1: inter-entry relationships (character↔organization membership, scene↔location), attribute change history/audit, and bulk "as of" timeline views across all entries.
