# 02 — The read page shell

`codex.show` exists and renders everything except attributes and referencing scenes.

## Scope

- Route `GET /codex/{codexEntry}` → `CodexEntryController::show`, named `codex.show`, placed
  after `codex.edit` among the flat member routes (`routes/web.php` ~199).
- `show()` authorizes `view` on the owning project and eager-loads `aliases`, `tags`, `media`,
  `attributeValues.startEvent`, `inceptionEvent`, `terminationEvent`.
- New `resources/views/codex/show.blade.php` holding, in order: header (cover, name, type
  label, aliases, tags), action row, description as rendered rich text, reference images,
  reference files, lifespan.
- Action row: Edit, Duplicate (the `x-icon-dialog-button` modal from `codex/index.blade.php`),
  History (`revisions.index`, entity `codex`), Delete.

Not in scope: the attribute and scene sections — task 03. Link changes — task 04.

## Depends on

01.

## Key decisions

- `show()` does **not** load what only the form needs: `events`, `regularEvents`,
  `windowMin`/`windowMax`, `projectTags`, `duplicateSuggestion`. That last one is a second name
  query per page load.
- Sections are omitted when empty, not rendered holding an em dash. A name-only entry renders
  as a name.
- `fields.blade.php` is not reused.

## Consult

`expanded/architecture.md` → Route, Authorization, What `show` does not load.
`expanded/ui.md` → the section order and the component reuse list.

## Tests

- Renders name, an alias, a tag, and the description.
- Lifespan appears when an inception or termination event is set, and is absent when neither is.
- Reference images and files appear; a media-less entry renders without those sections.
- Response carries no `name="name"` input and no autosave field.
- Non-owner gets 403; guest is redirected to login.
