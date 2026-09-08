# Architecture

## Route

```
POST /scenes/{scene}/codex-entries   →  scenes.codex-entries.store   (JSON)
```

Bound to the **scene**, not the project: the scene is what gets resynced and what the
response describes. Shallow, beside the other `/scenes/{scene}` routes.

## `App\Http\Controllers\SceneCodexEntryController::store()`

Single action. Resolve, authorize, delegate, return JSON — the same shape as
`FieldAutosaveController`.

1. `authorize('update', $scene->chapter->act->book->project)`.
2. `CodexEntrySaver::create($project, $type, ['name' => …], $uploads, rescanProject: false)`.
3. `SceneReferenceMatcher::syncScene($scene)`.
4. Return the created entry and the scene's refreshed reference list.

```json
{ "entry": { "id": 12, "name": "Melusine", "type": "character",
             "type_label": "Character", "url": "/codex/12" },
  "referenced_entries": [ { "id": 12, "name": "…", "type_label": "…", "url": "…" } ] }
```

Returning the whole refreshed list, not just the new entry, keeps the sidebar honest:
`syncScene()` may have added or dropped others since the last render. The client replaces
the list rather than appending — no optimistic state, so the source spec's "pending vs
resync" open end resolves to *resync, immediately*.

## `App\Services\CodexEntrySaver`

Add `bool $rescanProject = true` as the last parameter of `create()`. When false, skip
`syncProject()`. Existing callers are unchanged.

Comment why: a project-wide rescan is the cost this endpoint exists to avoid, and the
caller here syncs the one scene that matters.

`CodexMediaUploads` needs a blank instance. Check for an existing named constructor before
adding an `empty()`.

## `App\Http\Requests\StoreQuickCodexEntryRequest`

- `authorize()`: `can('update', ...)` on the scene's project.
- `name`: `required`, `string`, `max:255`, trimmed.
- `type`: `required`, `Rule::enum(CodexEntryType::class)`.
- `withValidator()`: reject a name that exactly matches an existing entry of the same type
  in the project, case-insensitively, with a message carrying the existing entry id so the
  client can offer *Open it*. Entry counts are small; a plain collection compare is fine.

The full form only *warns* on a duplicate. This one refuses, because there is no form left
to read the warning on. Note the divergence in the request docblock.

## Interaction with `codex-attributes-on-demand`

That feature stops `seedAttributeBaselines()` writing a row per attribute. Until it lands,
every quick-created entry gets fifteen blank rows — the exact thing this feature exists to
spare the writer. Either land it first, or accept the rows. See `open-questions.md`.

## Untouched

`SceneReferenceMatcher`, `CodexEntryController`, the codex policies, `AttributeTimeline`.
