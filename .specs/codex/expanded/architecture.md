# Codex — Architecture (controllers, routes, authorization)

Follows the existing conventions exactly: thin controllers (`resolve → authorize → delegate → respond`), authorization walked up to `Project` via `ProjectPolicy`, shallow nested routes, Form Requests for validation, a service for non-trivial workflow.

## Controllers

### `CodexEntryController`

One controller serves all three entity types; the type is a **route segment** (`{type}` ∈ `characters|locations|organizations`), resolved to `CodexEntryType` via `routeKey()`. This is the DRY choice — the columns and flows are identical and only the enum differs. (Per-type controllers are the alternative; see [`open-questions.md`](open-questions.md).)

- `index(Request, Project, string $type)` — `authorize('view', $project)`; filter `CodexEntry::where('project_id',…)->where('type', …)`; support `search` (name **and** aliases via `orWhereHas('aliases', …)`), `tag` filter, and `sort`/`direction` — **all in the controller** `index`, not query scopes (guidelines). Eager-load `tags`, `cover`. Render `codex.index`.
- `create(Project, string $type)` — `authorize('update', $project)`; render `codex.create` with the applicable attributes (`CodexAttribute` where `applies_to` contains the type) and project events (for anchoring). 
- `store(StoreCodexEntryRequest, Project, string $type)` — create entry; `resolveTags()` sync; sync aliases; hand uploaded files to `CodexMediaService`; seed attribute baselines from the validated `attribute_baselines[]` input via `AttributeTimeline::ensureBaseline` (empty baseline for applicable attributes not submitted). Wrap writes in a `DB::transaction` (guidelines: multi-step writes).
- `edit(CodexEntry)` — `authorize('update', $entry->project)`; eager-load `aliases`, `tags`, `media`, `attributeValues.startEvent`; render `codex.edit`.
- `update(UpdateCodexEntryRequest, CodexEntry)` — same delegation as store.
- `destroy(CodexEntry)` — `authorize('update', $entry->project)`; delete (media files cleaned up in the `deleting` hook / service — see data-model warning).

### `CodexAttributeController`

Project-scoped administration of attribute *definitions* ("create new attributes and pick which entities they show up on").

- `index/create/store/edit/update/destroy` under the project, authorizing via `ProjectPolicy`.
- `store`/`update` take `StoreCodexAttributeRequest` validating `name` and `applies_to[]` (each `Rule::enum(CodexEntryType::class)`, at least one).
- `destroy` cascades its values (FK `cascadeOnDelete`); confirm in UI since it drops timeline data.

### `CodexAttributeValueController` — timeline edits

Handles adding/updating/removing a period for one (entry, attribute). Kept separate from the entry form so the timeline editor can post independently (and later be AJAX, like the move-up/down `wantsJson()` pattern).

- `store(StoreAttributeValueRequest, CodexEntry, CodexAttribute)` → `AttributeTimeline::upsertAt`. **`store` is an upsert**: posting an anchor event that already has a value updates that value in place (`updateOrCreate`). This is deliberate — it's also how existing period values (including the Start baseline) are edited, so there is **no `update` action or route** for values.
- `destroy(CodexAttributeValue)` → `authorize('update', $value->entry->project)` → `AttributeTimeline::removeAt`.

All timeline invariants live in `App\Services\AttributeTimeline` (see [`attribute-timeline.md`](attribute-timeline.md)), **not** the controller.

## Services

- `App\Services\AttributeTimeline` — temporal resolution + gap-free mutations (detailed in the timeline doc). This is the project's first `app/Services` class; guidelines explicitly anticipate creating it here.
- `App\Services\CodexMediaService` — validates already-passed the FormRequest, stores files on the `public` disk (`Storage::disk('public')->putFileAs(...)`), enforces the single-cover rule (replaces existing `Cover` row + deletes its file), assigns `position`, and deletes files on media/entry removal. Centralizes the storage path + naming so it isn't hard-coded in controllers (guidelines: config in one place).

## Form Requests (`app/Http/Requests`)

Mirror `authorize()` with `$this->user()->can(...)` (guidelines) and infer rules from the schema:

- `StoreCodexEntryRequest` / `UpdateCodexEntryRequest` — `type` via `Rule::enum` (store; from route on create), `name` required, `description` nullable, `aliases` array of strings, `tags` array of strings, `cover` image rules, `reference_images[]` image rules, `reference_files[]` file rules. Also `attribute_baselines` — an array keyed by attribute id (`attribute_baselines[<codex_attribute_id>] => string`), used on create to seed each applicable attribute's Start-anchored value (see [`ui.md`](ui.md)): validate each key `Rule::exists('codex_attributes','id')->where('project_id', …)` **and** (custom rule or `withValidator` closure, since `applies_to` is JSON filtered in PHP) that the attribute's `applies_to` contains the entry's type — the same cross-project rigor applied to `start_event_id`. Centralize the upload rules (mimes, `max` KB) — a shared `array` of rules or a config constant, referenced by both requests (guidelines: avoid duplicated validation, no magic numbers). Reuse `ValidMarkdown` on `description` if descriptions are Markdown (they render like scene contents).
- `StoreCodexAttributeRequest` / `UpdateCodexAttributeRequest` — `name` required; `applies_to` array, `applies_to.*` `Rule::enum(CodexEntryType::class)`.
- `StoreAttributeValueRequest` — `start_event_id` `Rule::exists('events','id')->where('project_id', …)` (same pattern as `StoreSceneRequest`); `value` string. **No `Rule::unique`** — the endpoint is an upsert (see controller above); the DB unique constraint on `(entry, attribute, start_event)` remains as a backstop only.

## Routes (`routes/web.php`, inside the `auth` group)

Manual shallow nesting (the `{type}` segment doesn't fit `Route::resource` cleanly, and edit/update/destroy only need the entry):

```php
// Entries — nested index/create/store carry {type}; edit/update/destroy are flat.
Route::get('/projects/{project}/codex/{type}', [CodexEntryController::class, 'index'])->name('projects.codex.index');
Route::get('/projects/{project}/codex/{type}/create', [CodexEntryController::class, 'create'])->name('projects.codex.create');
Route::post('/projects/{project}/codex/{type}', [CodexEntryController::class, 'store'])->name('projects.codex.store');
Route::get('/codex/{codexEntry}/edit', [CodexEntryController::class, 'edit'])->name('codex.edit');
Route::put('/codex/{codexEntry}', [CodexEntryController::class, 'update'])->name('codex.update');
Route::delete('/codex/{codexEntry}', [CodexEntryController::class, 'destroy'])->name('codex.destroy');

// Attribute definitions — project-scoped resource, shallow.
Route::resource('projects.codex-attributes', CodexAttributeController::class)
    ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])->shallow();

// Timeline period edits.
Route::post('/codex/{codexEntry}/attributes/{codexAttribute}/values', [CodexAttributeValueController::class, 'store'])->name('codex.attribute-values.store');
Route::delete('/codex-attribute-values/{codexAttributeValue}', [CodexAttributeValueController::class, 'destroy'])->name('codex.attribute-values.destroy');
```

Constrain `{type}`: `->whereIn('type', ['characters','locations','organizations'])` (or a route pattern), so an unknown type 404s before the controller. All routes stay under `auth` middleware.

## Authorization

- No new policies. Every action authorizes the owning `Project` through `ProjectPolicy` (`view`/`update`), reached via `$entry->project`, `$attribute->project`, `$value->entry->project` — exactly the "walk up to project" rule in the guidelines.
- Never rely on route-model binding alone: the `Rule::exists(...->where('project_id', …))` constraints on `start_event_id`, `tags`, and `type` prevent cross-project references even for an owner.

## Navigation (`resources/views/layouts/navigation.blade.php`)

Add a **Codex** dropdown between *Timeline* and *Story*, inside the existing `@if ($project = …)` block (both the desktop dropdown and the responsive section), with three `x-dropdown-link`s:
`route('projects.codex.index', [$project, 'characters'])`, `…'locations'`, `…'organizations'`. Extend the `$project` resolver chain to also derive the project from a bound `codexEntry` (`request()->route('codexEntry')?->project`) so the nav renders on codex edit pages.

## Documentation to update (guidelines require keeping docs in sync)

- `documentation/architecture.md` — new Codex aggregate, the single-table + type-enum decision, the timeline service.
- `documentation/glossary.md` — "Codex entry", "attribute definition", "attribute period / step function", "anchor event".
- `CHANGELOG.md` — `[Unreleased] → Added`.
- `CLAUDE.md` — a "Codex" section paralleling the Scene↔Event and position-ordering notes (types enum, gap-free invariant, `{type}` routes, seeding caveats).
