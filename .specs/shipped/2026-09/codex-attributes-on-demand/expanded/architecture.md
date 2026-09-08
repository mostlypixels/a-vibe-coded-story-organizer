# Architecture

## `App\Services\CodexAttributeSheets`

Owns both predicates. Nothing else may re-derive them.

```php
public function attached(CodexEntry $entry, Event $startEvent): Collection;  // rows exist
public function setOnly(CodexEntry $entry, Event $startEvent): Collection;   // a filled value exists
public function unattachedFor(CodexEntry $entry): Collection;                // picker options
```

- `attached()` — `forEntry()` rejecting sheets with no baseline and no periods. This is
  today's `setOnly()` body, renamed to what it really tests.
- `setOnly()` — rejects unless the baseline or one period has `filled($value->value)`.
  Same test `CodexAsOfResolver` applies; the drift closes here.
- `unattachedFor()` — `project->codexAttributesFor($entry->type)` minus attached ids,
  in `position` order.
- `forEntry()` stays as-is (still the "all applicable" view). Callers must load
  `attributeValues.startEvent` first — both controllers already do.

## `App\Services\CodexEntrySaver`

`seedAttributeBaselines()` loops the project's attributes and writes a row for each.
Replace with: iterate the **posted** `attribute_baselines` keys only, and skip a blank
one. A create then writes only what the writer typed.

Keep the loop over `codexAttributesFor($type)` for the type check — or drop it, since
`StoreCodexEntryRequest::withValidator()` already rejects a foreign or wrong-type
attribute id. Prefer dropping it: one guard, in the Form Request.

## New controller — `CodexEntryAttributeController`

Attach/detach is a different resource from a timeline period, so it does not belong in
`CodexAttributeValueController` (that one owns one anchored value).

| Verb | URI | Name | Action |
|---|---|---|---|
| POST | `/codex/{codexEntry}/attributes` | `codex.attributes.attach` | `ensureBaseline('')` for the posted attribute |
| DELETE | `/codex/{codexEntry}/attributes/{codexAttribute}` | `codex.attributes.detach` | delete that pair's rows |

Both redirect to `codex.edit`. Shallow, matching the existing attribute-value routes.

- Authorize `update` on `$codexEntry->project`, mirrored in the Form Request.
- `abort_unless($codexAttribute->project_id === $codexEntry->project_id, 404)` — the same
  cross-project guard `CodexAttributeValueController::store()` already carries.
- Attach also aborts 422 when the attribute does not apply to the entry's type.
- Detach is a plain `delete()` on `$entry->attributeValues()->where('codex_attribute_id', …)`.
  It bypasses `AttributeTimeline::removeAt()` on purpose: that method guards the Start
  baseline against leaving a hole, and detaching removes the whole pair, so there is no
  hole to leave. Say so in a comment.

## New Form Request — `AttachCodexAttributeRequest`

- `authorize()`: `can('update', $this->route('codexEntry')->project)`.
- `codex_attribute_id`: `required`, `integer`,
  `Rule::exists('codex_attributes', 'id')->where('project_id', $project->id)`.
- The type check goes in `withValidator()`, using `CodexAttribute::appliesTo()` — a
  validation error, not a 422 abort, so the picker can show it. (Supersedes the abort
  above.)

## Untouched

`AttributeTimeline`, `CodexAsOfResolver`, `CodexEntryDuplicator`, `CodexAttributeController`,
`StoreAttributeValueRequest`, the codex policies.

## Documentation

`documentation/features/codex.md` → *Temporal attributes*: add the two predicates and
what a row's presence means. One short subsection, not a rewrite.
