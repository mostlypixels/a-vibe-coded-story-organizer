# 05 — Mode switch

## Scope

Let the owner change `overview_render_mode` from the overview page.

- **Route**: `PATCH /projects/{project}/story/overview/mode` →
  `StoryController::updateMode`, name `projects.story.overview.mode`. Mirror the
  per-project `data.publication-settings.update` posture.
- **Form Request** `UpdateStoryOverviewModeRequest`: `authorize()` →
  `$this->user()->can('update', $project)`; rule
  `Rule::enum(StoryOverviewMode::class)`.
- **Controller**: `authorize('update', $project)`, persist, redirect back to the
  overview (preserve the current `?chapter=` if present).
- **UI**: an owner-only control in the overview header (beside `x-word-count`) —
  a labelled `<select>` (or segmented control) submitting on change; the two
  `StoryOverviewMode` labels. Render only when the user can `update` the project;
  a view-only user sees the owner's mode with no control.

Does **not**: add more settings; no project-edit-page control.

## Depends on

01, 03 (the modes must exist and render). 04 not strictly required but ships
before this in order.

## Key decisions

- Overview header, owner-only. PATCH mirrors `PublicationSettingController`.
- Presentation logic stays out of Blade — build the option list from the enum.

## Consult

`../expanded/ui.md` → "Mode switch"; `../expanded/architecture.md` →
"StoryController::updateMode".

## Tests (extend `StoryTest`)

- Owner PATCH with a valid mode → persisted, redirect back.
- Invalid enum value → `assertSessionHasErrors`.
- Non-owner PATCH → 403.
- Overview response shows the switch for the owner, hides it for a
  (hypothetical) view-only user.
