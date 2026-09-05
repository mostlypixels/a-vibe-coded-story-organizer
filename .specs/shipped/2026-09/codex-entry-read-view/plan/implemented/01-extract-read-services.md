# 01 — Extract the read services

Pure refactor. No route, no view, no behaviour change.

## Scope

- Move `CodexEntryController::referencingScenesInTimelineOrder()` (line ~179) to
  `App\Services\ReferencingScenes`.
- Move `CodexEntryController::timelineSheets()` (line ~196) to
  `App\Services\CodexAttributeSheets`, as `forEntry(CodexEntry $entry, Event $startEvent)`.
- Add `CodexAttributeSheets::setOnly(CodexEntry $entry, Event $startEvent)`: the same
  collection, minus attributes with no baseline and no periods.
- `edit()` calls both services and is otherwise untouched.

Not in scope: `setOnly()` has no caller yet — task 03 uses it.

## Depends on

Nothing.

## Key decisions

- Two classes, not one. They answer different questions and `ReferencingScenes` is reusable by
  the scene and event read pages later.
- `setOnly()` lives beside `forEntry()` rather than being a filter at the call site, so both
  callers agree on what "set" means.

## Consult

`expanded/architecture.md` → Extractions.

## Tests

- `ReferencingScenes` returns the documented order: evented scenes by `event_datetime` then
  event id, then unevented scenes by act, chapter, scene position. Assert against the service
  directly.
- `CodexAttributeSheets::forEntry()` includes an attribute the entry has no value for;
  `setOnly()` omits it and keeps one that has only a baseline.
- Existing `codex.edit` tests stay green.
