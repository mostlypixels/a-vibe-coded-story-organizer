# 05 — Codex edit UI

## Scope

- New **"Existence"** card on `resources/views/codex/edit.blade.php`, rendered only when
  `$type->tracksLifespan()`. Two field groups (inception, termination), each an event `<select>`
  (regular events, bookends excluded, "— Not set —" option) plus a "+ New event" Alpine toggle with
  `new_*_title` / `new_*_datetime` (datetime-local, `min`/`max` = window bounds). Labels from
  `$type->inceptionLabel()` / `$type->terminationLabel()`.
- Extract the inline event-picker markup shared by the scene page and these two groups into a
  reusable Blade partial/component; repoint `resources/views/scenes/edit.blade.php` at it. Only if
  the copy is non-trivial (`open-questions.md` #9) — otherwise keep it local and note why.
- Inverted-lifespan warning: when `$entry->hasInvertedLifespan()`, show a small muted note under
  the termination field ("Termination is before inception, so age is not calculated. Track age with
  an attribute instead."). Server-rendered from saved state, not live Alpine.

Does **not**: change the resolver or the as-of panel (task 06), or add fields to the create form.

## Depends on

02 (`tracksLifespan`, `hasInvertedLifespan`, labels), 04 (controller passes events + saves links).

## Key decisions

- Card gated per type; no "not yet" label anywhere (`open-questions.md` #2, #5).
- Warning is static from `hasInvertedLifespan()`, updates on next save.

## Consult

`expanded/ui.md`. Existing scene edit "Happens during" block for the picker markup.

## Tests

Feature, per `expanded/testing.md` → *Inverted-lifespan warning* / *Per-type gating*:

- Codex edit page for a lifespan type renders the Existence card with both labelled fields.
- Termination before inception → the warning is present; normal order → absent.
- Scene edit page still works after the picker extraction (its inline "New event" test stays
  green).
