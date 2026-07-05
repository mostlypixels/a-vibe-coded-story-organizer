# Codex — UI (views & components)

Reuse the existing component families (`x-table*`, `x-card`, `x-input-*`, icon buttons, `x-event-picker`, `x-color-picker`). New markup should read like the current `events`/`scenes` views. Colors follow the app's `ocean-*`/`navy-*`/`aqua-*` palette already in those views.

## Navigation

Add the **Codex** dropdown as described in [`architecture.md`](architecture.md#navigation) — three links to the three type indexes, in both the desktop and responsive menus, guarded by the same `$project` resolver (extended to cover `codexEntry`).

## Index pages — `resources/views/codex/index.blade.php`

One view for all three types (title/labels driven by the `CodexEntryType` passed in). Mirror `scenes/index.blade.php`:

- `x-heading` with the type's plural label + a "New {Character/Location/Organization}" button to `projects.codex.create`.
- Search box (`search`) — placeholder "Search by name or alias…". Optional `tag` filter `<select>` built from the project's tags.
- `x-table` with `x-sortable-header`/`x-table-heading` columns: **cover thumbnail**, **Name**, **Aliases** (comma-joined), **Tags** (as `x-badge` chips), actions (`x-icon-edit-link`, `x-icon-delete-button`).
- `x-table-row :striped="$loop->even"`, `x-table-empty :colspan="…"` for no results.
- No move up/down (codex entries aren't position-ordered).

## Attribute definitions admin — `resources/views/codex-attributes/`

- `index` — `x-table` of attributes: Name, **Applies to** (badges for each `CodexEntryType`), edit/delete. "New attribute" button.
- `create`/`edit` — `x-card` form: `name` text input + a set of checkboxes for `applies_to[]` (one per `CodexEntryType`, pattern copied from the plotlines checkbox block in `events/edit.blade.php`). Delete confirms (drops timeline data).

## Entry edit/create — three-column layout

`resources/views/codex/edit.blade.php` (+ `create.blade.php`). The spec's `col-8 / col-2 / col-2`. Tailwind (no Bootstrap grid in this app) → a 12-col CSS grid:

```
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
  <div class="lg:col-span-8"> … main form … </div>   {{-- left --}}
  <div class="lg:col-span-2"> … tags & categories … </div> {{-- middle --}}
  <div class="lg:col-span-2"> … media … </div>        {{-- right --}}
</div>
```

Single `<form method="POST" enctype="multipart/form-data">` wrapping all three columns so one Save persists everything (uploads require `enctype`). The delete form and the attribute-timeline editor post separately (see below).

### Left column (`col-8`) — main content + attribute sheet

- `name` (`x-text-input`, required), `description` (`textarea`; Markdown like scene contents if `ValidMarkdown` is applied).
- **Aliases**: a small Alpine repeater of text inputs submitting `aliases[]` (add/remove rows) — model it on the `x-event-picker` chip pattern but for free text, or a simpler add-a-row list. Consider extracting an `x-string-list` component (reuse candidate).
- **Attribute sheet**: for each applicable `CodexAttribute`, render its **timeline of periods** (see below). On `create`, only the Start-baseline field is shown (values for other events are added after the entry exists, since periods need an entry id).

### Middle column (`col-2`) — tags & categories

- Tag chip input submitting `tags[]`, allowing existing tags (autocomplete from the project's tags, embedded as JSON like `x-event-picker`) and new tag names (created via `firstOrCreate`). Reuse/generalize the `x-event-picker` chip UI into an `x-tag-picker` or a shared `x-chip-picker`.

### Right column (`col-2`) — media

- **Cover image**: current thumbnail (if any) + a single `<input type="file" name="cover" accept="image/*">`; uploading replaces the existing cover.
- **Reference images**: `<input type="file" name="reference_images[]" multiple accept="image/*">` + a gallery of existing ones with per-item delete.
- **Reference files**: `<input type="file" name="reference_files[]" multiple>` + a list of existing files (download link via `original_name`) with per-item delete.
- Show accepted types / max size hints from the centralized upload config. Per-item deletes can post to a small media destroy route or be toggled via hidden `remove_media[]` inputs processed on save — pick one and keep it consistent (recommend hidden `remove_media[]` to keep everything in the single save).

## Attribute timeline editor (per attribute, left column)

Renders one attribute's periods chronologically (from `AttributeTimeline::periods()`), e.g.:

```
Hair color
  ● Start          [ blonde        ]              (event locked)
  ● Halloween      [ green         ]   [× remove]
  ● Back to class  [ black         ]   [× remove]
  + Add period at event…  (event picker) [ value ] [Add]
```

- Each row shows the **anchor event** label + a value input. Each existing row is its own small `<form>` posting to `codex.attribute-values.store` with the anchor event in a hidden `start_event_id` input — the store endpoint is an **upsert**, so this both adds new periods and saves edits to existing ones (there is no separate update route). The **Start** row's event is locked and has **no remove** button (parallels how `is_fixed` events / the main plotline hide delete affordances); its value is edited via the same upsert.
- "Add period" uses the searchable event picker pattern (`x-event-picker` JSON+Alpine filtering) to choose the anchor event, then a value field. Also posts to `codex.attribute-values.store`.
- Remove posts to `codex.attribute-values.destroy` (confirm).
- Keep presentation logic out of Blade (guidelines: "presentation logic out of templates") — the ordered periods and each period's computed *end* (next anchor, for display) come pre-computed from the service, not calculated in the view.

> [!NOTE]
> Because periods reference an entry id, the full timeline editor only appears on **edit**, not **create**. On create, capture just the Start-baseline value per applicable attribute as `attribute_baselines[<codex_attribute_id>]` inputs (validated in `StoreCodexEntryRequest` — see [`architecture.md`](architecture.md)); the controller calls `ensureBaseline` with each.

## "As of" display (read-only resolution)

- On `scenes/edit.blade.php` (or the Story overview), optionally show a panel "Codex as of this scene": for the scene's **happens-during** event, list characters/locations with each attribute resolved via `CodexEntry::attributeValueAt($attribute, $scene->event)`. If the scene has no event, show a muted "—" (the scene is already red-bordered when unassigned).
- On `events/edit.blade.php`, a similar "Values as of this event" panel is a natural fit next to the existing "Scenes happening during" section.

## Accessibility & semantics (guidelines)

- Semantic HTML; every file input and value field has an `x-input-label`.
- The timeline editor is keyboard-navigable (the event picker already supports Enter/Escape).
- Cover/reference images need `alt` text (fall back to the entry name).
