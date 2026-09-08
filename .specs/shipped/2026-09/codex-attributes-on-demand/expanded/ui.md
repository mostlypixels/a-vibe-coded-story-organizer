# UI

## `resources/views/codex/partials/fields.blade.php` — create form

Delete the whole `@if ($entry === null && $attributes->isNotEmpty())` Attributes card and
replace it with a picker card that starts empty.

- Alpine, no round-trip: `x-data` holds the chosen attribute ids; each pick appends a
  labelled `attribute_baselines[{id}]` text input, with a Remove beside it.
- Options come from `$attributes` (already passed by `CodexEntryController::create()`),
  minus the ones already picked.
- Card is present but collapsed to one line when nothing is picked — the writer must be
  able to see the door without being asked to walk through it.
- Restore picks from `old('attribute_baselines')` after a validation failure.
- Hide the card when the project defines no attributes for the type.

Reuse `x-select` + `x-button`, not `x-chip-picker` — chip-picker is a multi-select of
labels with no value box per pick.

## `resources/views/codex/partials/attribute-timeline.blade.php` — edit form

`$sheets` becomes `attached()` instead of `forEntry()`. The `@foreach` body is unchanged.

Add, per attribute block, next to the `<h3>`: an `x-icon-delete-button` posting
`codex.attributes.detach`, confirm text naming the loss — *"Remove Hair colour from
Melusine? Its values are deleted. The attribute stays in the project."*

Add, below the blocks, one attach form: `x-select` of `$unattachedAttributes` +
`x-button` Add, posting `codex.attributes.attach`.

The card's `@if ($sheets->isNotEmpty())` guard must go — the card now has to render for an
entry with zero attributes so the attach form is reachable. Empty state: one muted line,
then the picker.

## Discoverability

One muted line under the picker on both forms:

> These come from your project's attributes. [Add or remove attributes](…)

Links `projects.codex-attributes.index`. This is the only text that tells the writer the
list is hers.

## Controller view data

- `create()` — keep `attributes`; it is now picker options.
- `edit()` — `sheets` → `$sheets->attached(...)`, plus
  `unattachedAttributes` => `$sheets->unattachedFor($codexEntry)`.
- `show()` — unchanged call, but `setOnly()`'s new predicate changes what it returns.

## Accessibility

Each generated value input gets a real `<label>` (the create form's current one is
visible; keep that). The attach `x-select` needs an `sr-only` label, as the existing
"Add period at…" select does.
