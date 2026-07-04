# Codex plan — 06 · Timeline editor: attribute value endpoints + sheet UI

## Goal

The attribute sheet comes alive: entry edit pages show each applicable attribute's chronological periods with inline editing (upsert), add-period, and remove; entry create pages capture Start baselines. This wires task 02's service to HTTP.

## Depends on

02 (service), 03 (entry pages), 05 (attribute definitions).

## Spec references

- [`../attribute-timeline.md`](../attribute-timeline.md) — upsert semantics, editing UX contract.
- [`../architecture.md`](../architecture.md) — `CodexAttributeValueController`, `StoreAttributeValueRequest`, `attribute_baselines` validation.
- [`../ui.md`](../ui.md) — timeline editor sketch.

## Files to create/modify

### Routes

- `POST /codex/{codexEntry}/attributes/{codexAttribute}/values` → `codex.attribute-values.store`
- `DELETE /codex-attribute-values/{codexAttributeValue}` → `codex.attribute-values.destroy`

### `app/Http/Controllers/CodexAttributeValueController.php`

- `store(StoreAttributeValueRequest, CodexEntry, CodexAttribute)` — **upsert** via `AttributeTimeline::upsertAt` (posting an existing anchor updates its value; this is also how the Start baseline is edited — there is deliberately **no update route**). Guard that the attribute belongs to the entry's project.
- `destroy(CodexAttributeValue)` — `authorize('update', $value->entry->project)` → `removeAt` (service refuses to drop the Start baseline while others exist → translate to a 403/validation error).

### `app/Http/Requests/StoreAttributeValueRequest.php`

`start_event_id` → `Rule::exists('events','id')->where('project_id', …)` (pattern from `app/Http/Requests/StoreSceneRequest.php`); `value` required string. **No `Rule::unique`** — DB constraint is a backstop only.

### Entry create — baselines

- `resources/views/codex/create.blade.php`: for each applicable attribute (via `CodexAttribute::appliesTo()`), one `attribute_baselines[<id>]` text input labeled "<name> (from Start)".
- `StoreCodexEntryRequest`: add `attribute_baselines` array rules — each key `Rule::exists('codex_attributes','id')->where('project_id', …)` plus a `withValidator`/custom-rule check that the attribute's `applies_to` contains the entry's type (JSON column → check in PHP).
- `CodexEntryController@store`: seed via `AttributeTimeline::ensureBaseline` per submitted attribute (empty baseline for applicable attributes not submitted), inside the existing transaction.

### Entry edit — timeline editor (`resources/views/codex/edit.blade.php`, left column)

Per attribute, render `AttributeTimeline::periods()` (pre-computed in the controller — no timeline math in Blade):

- Each existing period = its **own small `<form>`** posting to the store route with hidden `start_event_id` + a value input (upsert saves edits). Start row: event label locked, **no remove button** (parallels `is_fixed` events / main plotline).
- Remove button per non-Start row → destroy route, with confirm.
- "Add period" row: anchor chosen via the event-picker pattern (single-select; reuse/adapt `x-chip-picker` from task 04 or `x-event-picker` directly) + value input.
- `CodexEntryController@edit` eager-loads `attributeValues.startEvent` and passes ordered periods per attribute.

## Key decisions already made

Store-as-upsert, no update route, no `Rule::unique`; canonical `(event_datetime, events.id)` ordering comes from the service; Start baseline protected; full editor on **edit** only (create captures baselines — the accepted two-step flow).

## Tests — extend `tests/Feature/AttributeTimelineTest.php`

HTTP-level cases per [`../testing.md`](../testing.md): store creates a period; store at an existing anchor **updates** (one row, new value, no error); cross-project `start_event_id` rejected; destroy removes a period and resolution falls back to the previous value; Start-baseline destroy refused while others exist; non-owner 403 on store/destroy; `attribute_baselines` — seeds Start values on entry create, rejects attribute ids from another project or with non-matching `applies_to`.

## Done when

Hair-color scenario works in the browser end-to-end (baseline on create → add Halloween/Back-to-class periods → edit a value inline → remove a period), suite green, pint clean.
