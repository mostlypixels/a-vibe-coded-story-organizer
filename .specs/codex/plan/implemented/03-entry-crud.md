# Codex plan — 03 · Entry CRUD: routes, controller, requests, index + basic forms, nav

## Goal

Characters/Locations/Organizations are fully browsable and editable: index with search/filter/sort, create/edit/delete, Codex nav dropdown. Forms are functional but plain — polished pickers (04), attribute baselines (06), and media (07) are explicitly staged later.

## Depends on

01. (Not 02 — no timeline logic on these pages yet.)

## Spec references

- [`../architecture.md`](../architecture.md) — controller, routes, form requests, nav.
- [`../ui.md`](../ui.md) — index columns, three-column layout.

## Files to create/modify

### Routes (`routes/web.php`, inside the `auth` group)

Manual shallow nesting per [`../architecture.md`](../architecture.md) — nested `index`/`create`/`store` carry `{type}`, flat `edit`/`update`/`destroy`:

- `GET /projects/{project}/codex/{type}` → `projects.codex.index`
- `GET /projects/{project}/codex/{type}/create` → `projects.codex.create`
- `POST /projects/{project}/codex/{type}` → `projects.codex.store`
- `GET /codex/{codexEntry}/edit`, `PUT /codex/{codexEntry}`, `DELETE /codex/{codexEntry}` → `codex.edit` / `codex.update` / `codex.destroy`

Constrain `{type}` with `->whereIn('type', [...routeKeys])` so unknown types 404 before the controller.

### `app/Http/Controllers/CodexEntryController.php`

One controller for all three types; resolve `{type}` → `CodexEntryType::fromRouteKey()`. Thin actions (resolve → authorize → delegate → respond):

- `index` — `authorize('view', $project)`; filter `where('project_id')->where('type')`; `search` matches **name or alias** (`orWhereHas('aliases', ...)`), optional `tag` filter, `sort`/`direction` — all in the controller (guidelines; same shape as `EventController@index`). Eager-load `tags`, `aliases`.
- `create` — `authorize('update', $project)`.
- `store` — create entry, sync aliases, `resolveTags()` (per-project `firstOrCreate`, analogous to `SceneController::resolveHappensDuringEvent` at `app/Http/Controllers/SceneController.php:168`), inside `DB::transaction`.
- `edit` — `authorize('update', $entry->project)`; eager-load `aliases`, `tags`.
- `update` — same delegation as store.
- `destroy` — cascades handle children (media **file** cleanup is task 07's concern; note a TODO referencing it).

### Form Requests

`StoreCodexEntryRequest` / `UpdateCodexEntryRequest` — `authorize()` mirrors the policy check; rules: `name` required, `description` nullable + `ValidMarkdown` (`app/Rules/ValidMarkdown.php`), `aliases` array of strings, `tags` array of strings. **Staged**: media rules land in task 07, `attribute_baselines` in task 06 — leave a comment marking the extension points.

### Views (`resources/views/codex/`)

- `index.blade.php` — one view for all types (labels from the enum). `x-heading` + "New {label}" button, search box ("Search by name or alias…"), optional tag `<select>`, `x-table` with `x-sortable-header`/`x-table-heading`: Name, Aliases (comma-joined), Tags (`x-badge`), actions (`x-icon-edit-link`, `x-icon-delete-button`). No move buttons (not position-ordered). Cover thumbnail column arrives in task 07.
- `create.blade.php` / `edit.blade.php` — the three-column grid shell from [`../ui.md`](../ui.md) (`lg:grid-cols-12`, spans 8/2/2) in a single `<form>`; left column: `name`, `description`; middle: plain `tags[]` text inputs (placeholder for task 04's picker); right: placeholder card ("Media — coming in a later step"); aliases as plain repeated `aliases[]` text inputs.

### Navigation (`resources/views/layouts/navigation.blade.php`)

Codex dropdown between *Timeline* and *Story* in **both** the desktop menu (~line 15 block) and the responsive menu (~line 133 block), with three links via `route('projects.codex.index', [$project, <routeKey>])`. Extend the `$project` resolver chain with `request()->route('codexEntry')?->project` so the nav renders on flat codex pages.

## Key decisions already made

One controller + `{type}` segment; search in the controller (no scopes); flat tags; Markdown description.

## Tests — `tests/Feature/CodexEntryTest.php`

Per [`../testing.md`](../testing.md): index per type + type isolation; search matches name **and** alias; store persists name/description/aliases/tags (existing tags reused, new ones project-scoped); update happy path; non-owner 403 on every action; missing `name` → `assertSessionHasErrors`; unknown `{type}` → 404; cross-project tag ids rejected; destroy cascades aliases/pivot/values.

## Done when

All three type indexes browsable end-to-end (create → list → search by alias → edit → delete), nav shows on nested and flat codex pages, suite green, pint clean.
