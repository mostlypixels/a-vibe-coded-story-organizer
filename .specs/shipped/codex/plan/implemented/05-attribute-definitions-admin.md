# Codex plan — 05 · Attribute definitions admin (CodexAttribute CRUD)

## Goal

Writers can create attribute definitions ("Hair color", "Terrain"…) and pick which entity types they appear on — the spec's "create new attributes and pick which entities they show up on."

## Depends on

01. (Independent of 03/04; only the nav link touches shared files.)

## Spec references

- [`../architecture.md`](../architecture.md) — `CodexAttributeController`, routes, requests.
- [`../ui.md`](../ui.md) — attribute admin views.
- [`../data-model.md`](../data-model.md) — `codex_attributes` schema.

## Files to create/modify

- **Routes** — `Route::resource('projects.codex-attributes', CodexAttributeController::class)->only(['index','create','store','edit','update','destroy'])->shallow()` in the `auth` group (standard shallow pattern, like `projects.plotlines`).
- **`app/Http/Controllers/CodexAttributeController.php`** — thin actions authorizing via `ProjectPolicy` (`view` for index, `update` for writes; walk up `$attribute->project` on flat routes). Index sorted by `position`.
- **`app/Http/Requests/StoreCodexAttributeRequest.php` / `UpdateCodexAttributeRequest.php`** — `name` required; `applies_to` required array min 1; `applies_to.*` `Rule::enum(CodexEntryType::class)`. `authorize()` mirrors the policy check.
- **Views `resources/views/codex-attributes/`** —
  - `index.blade.php`: `x-table` — Name, "Applies to" as `x-badge` per type, edit/delete actions; "New attribute" button.
  - `create.blade.php` / `edit.blade.php`: `x-card` form — `name` input + one checkbox per `CodexEntryType` for `applies_to[]` (checkbox block pattern from the plotlines section of `resources/views/events/edit.blade.php`). Delete uses a confirm dialog **warning that all timeline values for this attribute are dropped** (FK cascade).
- **Navigation** — add an "Attributes" link to the Codex dropdown (both menus) pointing at `projects.codex-attributes.index`.

## Key decisions already made

`applies_to` is a JSON enum array filtered in PHP (no pivot); position auto-assigned by the model hook from task 01; destroy cascades values at the DB level (no controller logic).

## Tests — `tests/Feature/CodexAttributeTest.php`

Per [`../testing.md`](../testing.md):

- Create with `applies_to = [character]` → appears on character create/edit and **not** on location/organization forms (assert once task 06 renders sheets; until then assert via the model helper `appliesTo()` and the admin index).
- Validation: empty `applies_to` rejected; non-enum value rejected.
- Non-owner 403 on every action.
- Deleting an attribute cascades its `codex_attribute_values`.

## Done when

Attributes manageable end-to-end in the browser, applies-to badges render, suite green, pint clean.
