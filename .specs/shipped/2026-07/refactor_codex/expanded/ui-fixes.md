# UI & validation fixes — findings 5, 6, 7

Three self-contained defects in the Codex Blade layer and one FormRequest rule. No new
components; every fix reuses `x-input-error` and the controller-passes-data convention.

## Finding 5 — timeline editor: invisible errors + unsavable empty values

**Where:** `resources/views/codex/partials/attribute-timeline.blade.php` and
`app/Http/Requests/StoreAttributeValueRequest.php:32`.

Two compounding problems:

1. The partial renders only `$errors->get('attribute_value')` (line 16 — the destroy-guard
   message). A failed **store** puts errors under `value` / `start_event_id`; nothing renders
   them, so to the writer Save silently does nothing.
2. `value` is `required`, but baselines are legitimately `''` (create form blank →
   `seedAttributeBaselines` → `ensureBaseline('')`). So an empty baseline renders an input
   whose Save can never succeed, and no value can ever be cleared back to empty.

### Fix — validation rule

In `StoreAttributeValueRequest::rules()`:

```php
'value' => ['present', 'string', 'max:255'],
```

> [!NOTE]
> `present` + `string` in Laravel: an empty text input submits `""`, which passes `string`.
> Verify `nullable` is *not* needed (an empty `<input type="text">` posts `""`, not null);
> if any client can post null, use `['present', 'nullable', 'string', 'max:255']` and cast
> with `(string)` before `upsertAt` (its signature is `string $value`).

This aligns the store endpoint with the create-form/baseline semantics ("empty = recorded as
blank"), and pairs with finding 1's `ensureBaseline()` default of `''`
(see `timeline-integrity.md`).

### Fix — error rendering

In `attribute-timeline.blade.php`, next to the existing `attribute_value` error (line 16):

```blade
<x-input-error :messages="$errors->get('value')" class="mt-2" />
<x-input-error :messages="$errors->get('start_event_id')" class="mt-2" />
```

Session errors are shared across the card's many small forms — acceptable at this scale (the
source spec allows it). If per-form attribution is wanted, use named error bags
(`->withErrors($validator, 'timeline_'.$attribute->id)`) — **not recommended**; it complicates
the FormRequest (`protected $errorBag`) for little gain. Flagged in `open-questions.md` Q3.

### Fold-in — preserve input on validation failure (lower-priority note)

The per-period forms don't use `old()`, so a failed submit clears what was typed. While
touching the partial, wire the value inputs through `old('value', ...)`:

- Baseline input (line 42): `:value="old('value', $sheet['baseline']?->value)"`
- Period inputs (line 64): `:value="old('value', $period->value)"`
- "Add period" row (lines 81-99): `old('start_event_id')` selected on the `<select>`,
  `old('value')` on the text input.

> [!WARNING]
> `old('value')` is shared across all the small forms too (single error bag/session). After a
> failed submit every value input would show the failed text. If that's unacceptable, this is
> the same trade-off as error keying above — resolve both the same way (Q3).

## Finding 6 — tag query in the Blade partial

**Where:** `resources/views/codex/partials/fields.blade.php:85` —
`:tags="$project->tags()->orderBy('name')->get()"` runs a query at render time, against
`.claude/guidelines.md` ("keep presentation logic out of Blade", eager-load rule).
`CodexEntryController@index` already passes `tags` correctly (`:59`); `create()` and `edit()`
don't.

### Fix

- `CodexEntryController@create` (`:71-77`) and `@edit` (`:113-122`) add
  `'projectTags' => $project->tags()->orderBy('name')->get()` to their view data.
- The partial consumes it with the same guard style it already uses for `$attributes`
  (line 7): `$projectTags = $projectTags ?? collect();` in the `@php` block, then
  `:tags="$projectTags"` on `x-tag-picker`.
- Name it `projectTags`, not `tags`, to avoid colliding with the entry's own `$entry->tags`
  usage in the partial (`$tagValues`, line 9) and with the index view's `tags` variable.

## Finding 7 — upload errors rendered only for file index 0

**Where:** `resources/views/codex/partials/fields.blade.php:130-131` and `:154-155` — the
reference images/files cards render `$errors->get('reference_images')` **and**
`$errors->get('reference_images.0')`. A failure on the second file lands under
`reference_images.1` and is never shown.

### Fix

Use the wildcard form the codebase already uses elsewhere
(`resources/views/codex-attributes/partials/fields.blade.php` renders
`$errors->get('applies_to.*')`):

```blade
<x-input-error :messages="$errors->get('reference_images')" class="mt-2" />
<x-input-error :messages="$errors->get('reference_images.*')" class="mt-2" />
```

- Replace the `.0` line with `.*` (drop the now-redundant second lookup — the `.0` one);
  same for `reference_files`. Keep the un-indexed array-level lookup: array-level rules
  (`max` items) key errors under the bare name.
- `$errors->get('x.*')` returns a keyed array of message arrays; confirm `x-input-error`
  flattens nested arrays (the `applies_to.*` precedent suggests it does — verify once in the
  component, `resources/views/components/input-error.blade.php`).
