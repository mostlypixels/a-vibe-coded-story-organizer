# Codex plan — 04 · Reusable tag & alias picker components

## Goal

Replace task 03's plain text inputs with polished, reusable Alpine components: a chip-style tag picker (autocomplete existing tags + free new names) and an alias repeater. **Behavior-preserving** — same `tags[]` / `aliases[]` payloads, zero controller/request changes.

## Depends on

03.

## Spec references

- [`../ui.md`](../ui.md) — middle column (tags) and aliases repeater.
- `resources/views/components/event-picker.blade.php` — the existing searchable chip input this generalizes.

## Files to create/modify

- **`resources/views/components/chip-picker.blade.php`** (new) — extract the generic mechanics from `x-event-picker`: options embedded as JSON, Alpine client-side filtering, chip add/remove, hidden `name[]` inputs, Enter/Escape keyboard handling. Parameterize what differs: option list, input name, whether **free-text values are allowed** (tags: yes; events: no).
- **`resources/views/components/tag-picker.blade.php`** (new) — thin wrapper over `x-chip-picker`: options = the project's tags as JSON, `name="tags[]"`, free text allowed (new names are `firstOrCreate`d server-side by task 03's `resolveTags()`).
- **`resources/views/components/string-list.blade.php`** (new) — small Alpine add/remove-row repeater of text inputs submitting `name[]`; used for aliases (`aliases[]`).
- **`resources/views/components/event-picker.blade.php`** — refactor to delegate to `x-chip-picker` (keeping its public interface identical so the scene form is untouched), *or* leave as-is if the extraction turns awkward — do not force it (guidelines: no abstraction before reuse is real; here reuse **is** real with tags, but the event picker rewrite is optional).
- **`resources/views/codex/create.blade.php` / `edit.blade.php`** — swap the plain inputs for `x-tag-picker` (middle column) and `x-string-list` (aliases).

## Key decisions already made

Chips submit hidden `name[]` inputs; filtering stays client-side (mirrors the documented `x-event-picker` tradeoff — server-side search only at thousands of rows).

## Tests

No new test file — the contract is unchanged. `tests/Feature/CodexEntryTest.php` (aliases + tags persistence, new-tag creation) must keep passing untouched. If the event picker is refactored onto the shared component, the scene form's mentioned-events flow must also still pass (`SceneController` sync — verify manually in the browser, since scenes lack feature tests).

## Done when

Tag chips and alias rows work in the browser (add, remove, submit, re-edit), keyboard accessible (Enter adds, Escape closes — parity with `x-event-picker`), suite green with no test edits, pint clean.
