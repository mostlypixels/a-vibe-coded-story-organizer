# Codex plan — 08 · "As of" panels on scenes & events (read-only resolution)

## Goal

The payoff view: opening a scene (via its "happens during" event) or an event shows every codex entry's attribute values **as resolved at that moment** — "open a scene during *Back to class* → hair is black."

## Depends on

02 (resolution), 03 (entries exist). Independent of 04–07.

## Spec references

- [`../attribute-timeline.md`](../attribute-timeline.md) — "Reading a value as of a scene", eager-loading note.
- [`../ui.md`](../ui.md) — "As of" display section.

## Files to create/modify

- **`app/Http/Controllers/SceneController.php@edit`** — when the scene has a happens-during event, load the project's codex entries with `attributeValues.startEvent` (+ their attributes) and pre-compute each entry's resolved values via the service/`CodexEntry::attributeValueAt` — **in the controller, not Blade** (guidelines: presentation logic out of templates; eager-load to avoid N+1 across entries).
- **`resources/views/scenes/edit.blade.php`** — a "Codex as of this scene" panel (collapsible card) listing entries grouped by type with `attribute: value` lines. When `scene->event` is null, show a muted "—" note ("assign an event to see codex values") — consistent with the existing red-border unassigned-scene affordance.
- **`app/Http/Controllers/EventController.php@edit`** + **`resources/views/events/edit.blade.php`** — the same panel as "Values as of this event", placed next to the existing "Scenes happening during" section; here the moment **is** the event, so the anchor-identity rule applies (the event's own anchored values win over datetime ties).
- Consider a small shared Blade partial/component (`x-codex-as-of` or `codex/_as-of.blade.php`) so the two panels don't duplicate markup — reuse per guidelines.

## Key decisions already made

Read-only in v1 (editing happens on the entry sheet); null scene-event → "undetermined", never a guess; resolution comes pre-computed from the service.

## Tests

Focused, per the coverage-gaps note in [`../testing.md`](../testing.md) — **no** full `SceneTest`:

- A scene whose event is *Back to class* resolves the attribute to "black" through `CodexEntry::attributeValueAt` and the panel renders it (`assertSee`).
- A scene with `event_id = null` renders the undetermined state, no crash.
- Event edit page shows the event's own anchored value (identity beats a same-datetime sibling anchor).

Add these to `AttributeTimelineTest` or a small `CodexAsOfTest` — keep it one coherent file.

## Done when

Both panels render correct values in the browser for the seed/demo scenario, no N+1 (check the debugbar/query log on a project with several entries), suite green, pint clean.
