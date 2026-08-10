# Duplicate entities — architecture

## Routes

Flat on the child, next to the existing `move-up` / `move-down` verbs in `routes/web.php`:

```php
Route::post('/scenes/{scene}/duplicate', [SceneController::class, 'duplicate'])->name('scenes.duplicate');
Route::post('/codex/{codexEntry}/duplicate', [CodexEntryController::class, 'duplicate'])->name('codex.duplicate');
```

POST, not PATCH: it creates a resource. One route per entity rather than a generic
`/duplicate/{entity}/{id}` — the slug-gated generic form exists for autosave because a *field*
registry is the gate there; here route-model binding already yields the typed model.

## Controller actions

Four lines each, reading like every other action — resolve, authorize, delegate, redirect:

```php
public function duplicate(DuplicateEntityRequest $request, Scene $scene, SceneDuplicator $duplicator): RedirectResponse
{
    $copy = $duplicator->duplicate($scene, $request->validated('name'));

    return redirect()->route('scenes.edit', $copy)->with('status', 'duplicated');
}
```

The copy's edit page is the destination from both entry points — the writer just named it and
means to work on it, and one rule beats branching on the referrer.

## Form Request — one class, both routes

`app/Http/Requests/DuplicateEntityRequest.php`

* `authorize()`: `$this->user()->can('update', RouteProject::resolve($this))`.
  `App\Support\RouteProject` already walks `{scene}` and `{codexEntry}` up to their project, so
  both routes share one request without inheritance and a future entity needs no new class.
* `rules()`: `['name' => ['required', 'string', 'max:255']]` — the same rule the Store requests
  use. No `unique` rule (see `data-model.md` → Name suggestion).

The Form Request is the single authorization check on these actions; there is no code path in the
controller that runs before validation, so no second `$this->authorize()` call.

## Services — two, not one

```php
App\Services\SceneDuplicator::duplicate(Scene $scene, string $name): Scene;
App\Services\CodexEntryDuplicator::duplicate(CodexEntry $entry, string $name): CodexEntry;
```

The two share only the name helper (which lives in `App\Support\DuplicateName`), so a single
`EntityDuplicator` holding both would be a namespace, not a service. Each owns one entity's rules
end to end and is testable on its own.

`SceneDuplicator` depends on `SceneReferenceMatcher`; `CodexEntryDuplicator` on
`CodexMediaService` and `SceneReferenceMatcher`.

### SceneDuplicator

One transaction: shift siblings, insert the scene row, re-attach `event_scene`, then
`$matcher->syncScene($copy)` — mirroring `SceneController::store`, which resyncs on every write.

### CodexEntryDuplicator — order of work (the pitfall)

The project convention is *DB inside the transaction, disk after commit* (see
`CodexEntryController::store`). Duplication inverts one half: the new rows must carry the new
paths, so the **file copies happen first** and are removed in a `catch` when the transaction
throws.

```
$copiedPaths = copy each source file to a fresh path;   // disk
try   { DB::transaction(fn () => insert entry + aliases + media + values + tag pivots); }
catch { $media->deleteFiles($copiedPaths); rethrow; }   // disk
```

Trade-off: a crash between the copy and the cleanup leaks orphan files — never a row pointing at
a missing file, which is the direction a user would see. The alternative (rows first, paths
patched after) leaves the entry visibly broken mid-flight and needs a second write pass.

After commit, `$matcher->syncProject($project)`: the copy introduces a new name and a copied alias
set, exactly the condition `CodexEntryController::store` rescans for.

> [!NOTE]
> Copied aliases mean the copy matches every scene the original matches, so those scenes list both
> entries in their sidebar until the writer edits the copy's aliases. Accepted: the aliases are
> real data worth keeping, and the doubling is visible and self-correcting.

## Position insertion

Add to `App\Models\Concerns\HasSiblingPosition`:

```php
public function makeRoomAfter(): int;   // shifts later siblings down by one, returns $this->position + 1
```

It belongs there: it needs `siblingScopeColumn()`, and `swapWithAdjacentSibling()` is already the
one home of position arithmetic across a sibling set. Runs inside the caller's transaction.

## Where nothing goes

* No model `booted()` hook — duplication is application workflow, not a lifecycle invariant.
* No logic in Blade. The suggested name is computed in the controller and passed to the view.
