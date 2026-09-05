# Architecture

No schema change. Routing, one controller action, and two extractions.

## Route

`routes/web.php`, beside the other flat codex member routes (~line 199). The group there uses
plain member routes, not `Route::resource` — follow it.

```php
Route::get('/codex/{codexEntry}', [CodexEntryController::class, 'show'])->name('codex.show');
```

Place it after `codex.edit` so `/codex/{codexEntry}/edit` keeps matching first.

## Authorization

`show` authorizes `view` on the owning project, matching `index`. `edit`, `update`, `destroy`
and `duplicate` keep `update`. That is the only place the two actions differ in their gate.

## Extractions

`CodexEntryController` holds two private methods that `show` needs verbatim. Move both out
rather than calling them from a second action.

| Now | Becomes | Used by |
|---|---|---|
| `referencingScenesInTimelineOrder()` (line 179) | `App\Services\ReferencingScenes` | `show`, `edit` |
| `timelineSheets()` (line 196) | `App\Services\CodexAttributeSheets` | `show`, `edit` |

`CodexAttributeSheets` keeps its current job — wrap `AttributeTimeline` per attribute and split
the baseline period from the rest — and gains the filtering `show` needs:

```php
public function forEntry(CodexEntry $entry, Event $startEvent): Collection;   // every attribute
public function setOnly(CodexEntry $entry, Event $startEvent): Collection;    // baseline or periods present
```

`edit` calls `forEntry()` and is otherwise unchanged. `show` calls `setOnly()` and renders every
period it returns — the read page shows the whole timeline, not a single value.

## What `show` does not load

`edit` assembles pickers and warnings that a read page has no use for: `events`,
`regularEvents`, `windowMin`/`windowMax` (`EventWindow::forRegularEvent`), `projectTags`, and
`duplicateSuggestion` (`DuplicateName::suggest`, which runs a second name query). Leave all of
them out of `show` — it is the cheaper action, and at 300 entries that matters.

`show` eager-loads `aliases`, `tags`, `media`, `attributeValues.startEvent`, `inceptionEvent`,
`terminationEvent` — the same set `edit` loads.

## Link changes

| File | Change |
|---|---|
| `resources/views/codex/index.blade.php` | cover link and name link → `codex.show`; keep `x-icon-edit-link` on `codex.edit` |
| `resources/views/codex/partials/as-of.blade.php` | entry name link → `codex.show` |
| `resources/views/scenes/edit.blade.php` (~185) | reference sidebar link → `codex.show` |
| `app/Enums/SearchDomain.php` | add `viewRoute()`, returning `codex.show` for the codex domains and `editRoute()` elsewhere |
| `resources/views/components/search/result-row.blade.php` | name link and `x-icon-view-link` → the new `viewRoute` prop |

`SearchDomain::editRoute()` stays as it is — other domains have no read page, so a single
method cannot answer both questions honestly.

## Post-save redirect

`CodexEntryController::update()` passes `['projects.codex.index', …]` as the done target. Change
it to `['codex.show', $codexEntry]`. The stay target stays `codex.edit`. `RedirectsAfterSave`
itself is untouched — the targets are per-controller, so no other entity changes.

`duplicate()` keeps redirecting to `codex.edit` on the copy: a fresh duplicate is named and
edited, not read.
