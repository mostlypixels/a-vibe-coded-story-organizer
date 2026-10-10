# Project notes — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

An exception log: a task that went to plan gets no entry, because the diff and the task
file already record what was built. Bullets under the headings below, root cause first.

## Feedback & decisions

- 2026-10-10 grill: all 14 open questions took the recommended answer.
- Duplicating a codex entry also links the copy to the same notes, for consistency with scenes.
  Scenes and codex entries are the only duplicable linkables.

## Deviations from the spec/plan

- `Scene` keeps a legacy `setNotesAttribute()` mutator, not an `Attribute` method named `notes()`.
  Reason: that method hid the `HasNotes::notes()` relation. Task 09 removes the mutator with the
  column. Until then `$scene->notes` is the old column; use `$scene->notes()` for the relation.
- `Notable` is a plain `Model`, not a `Pivot`, and the relations do not call `->using()`. PHPStan
  rejects the custom pivot class against the `MorphPivot` template, and nothing needs it.
- `Note::class => ['body']` is not in `RichTextFields` yet. A `body` set-mutator on `Note`
  sanitizes on write, so the entry waits for task 02.

- `ValidNoteCategoryParent` also checks subtree height, not only the new parent's depth. Moving a
  branch under a deep parent would otherwise push its children past level 3.
- `StarterNoteCategories` is not called by `SeedsGenreBundle` or `app:seed-project`. The task names
  only `ProjectController@store`, `OnboardingController@store` and `InstallsDemoProjects`.
  `InstallsDemoProjects` adds starters only to projects created in that run.
- Category routes use `->parameters(['note-categories' => 'noteCategory'])`. The default wildcard
  `note_category` does not match `$this->route('noteCategory')` in the Form Requests.
- `NoteCategoryDeleter` moves notes with the base query builder, so `updated_at` and the index
  order stay unchanged.
- `linkPicker` does not reload the page after a link or unlink. It fetches the page again and swaps
  the element under `refreshSelector`, so unsaved title edits stay. Unlink also goes through the
  picker (`unlink(url)`), not a form. Config: entity side passes `linkType`, `linkId` and a
  `storeUrl` with `__NOTE__`; note side passes `type`.
- Link store and destroy answer 204 to a JSON request and redirect back otherwise.
- Scene notes migration skips only null or whitespace values. An editor-empty value such as
  `<p></p>` still becomes a note. Reason: a strip-tags check could drop a note that holds only
  an image or a table.
- Tests that used `Scene.notes` as a second rich field now use `description` or `contents`.
  `RevertSaveTest` moved to Book (`description`, `dedication`, `rights`): it needs three fields,
  and Scene now has two.
- `NoteLinkType` gained `noteRelation()`, `labelColumn()` and `labelFor()`; `Note` gained
  `linkTo()`, `unlink()` and `linkGroups()`. The read page shows the **Linked to** card only when
  the note has links.
- Search: notes get their own `SearchSection::Notes` (one table), after Codex. The task named no
  section; `SearchDomain::section()` needs one. A book filter hides notes, like plotlines and
  events, so the "belong to the whole project" line now names notes too.
- Old scene `notes_file` values become notes in the Notes import phase, not in `importScene()`. The
  phase reads the scene descriptors again and resolves the scene through `$idMaps`. It does not check
  the manifest version: any scene `notes_file` maps. A blank value makes no note, as in the migration.
- The importer also rejects a category tree deeper than `NoteCategory::MAX_DEPTH` and a parent that
  never resolves (absent or a cycle). Categories may come in any order in `categories.json`.
- `StoreNoteRequest` and `StoreNoteCategoryRequest` gained `fieldRules()`, like the other Store
  requests, so `ArchiveValidator` checks note titles and category names.
- `x-notes-card` is a class component (`App\View\Components\NotesCard`) that takes `:notes` and `:linkable`
  and resolves the project itself. Reason: Act, Chapter and Scene reach their project in three
  different ways, and the views would repeat that. A scene view must pass
  `$scene->getRelation('notes')`: `$scene->notes` is still the legacy column.
- Until the legacy column goes, the scene read and edit pages show two cards titled "Notes": the old
  rich field and the new linked-notes card.
- `NoteOutline` adds anchors with a regex over the `h1`–`h4` tags, not `DOMDocument`. Reason: a DOM
  round trip rewrites the whole body markup; the regex touches only the heading open tags. An `id`
  already on a heading is replaced, so it cannot clash with a generated anchor.
- Demo notes come from `SeedsNotes`, used by the three Melusine seeders (3 notes each, a nested category under Research). The trait calls `StarterNoteCategories::createFor()` itself, because the seeders run before the installer adds starters. The installer call stays; it is then a no-op for new demo projects.
- Task 11 left the `CHANGELOG.md` entry to ship-pr, on the caller's order.

## Issues → resolutions

- The shared category dialog failed to open when a test clicked "New category" right after a
  page load, before Alpine started. This is a browser-script race, not an app bug. Wait a moment
  after load in browser runs. The tests could not show it.
- `$refs.name` in the dialog root handler is out of scope: `x-modal` is a nested Alpine
  component. The select-on-open handler sits on the form instead, as in `duplicate-dialog`.

- `Scene::notes()` was undefined after adding `HasNotes`. Root cause: the `notes()` `Attribute`
  method for the old column overrode the trait method. Fixed as in Deviations. The first
  lifecycle test run caught it.
- Registering `note` in `AutosavableFields::REGISTRY` broke every test and `migrate`: migration
  `2026_09_23_000000_delete_orphan_revisions` reads the registry live and queried the `notes`
  table before its migration ran. Fixed with the `Schema::hasTable()` guard that
  `backfill_baseline_revisions` already uses. Only a full-suite run showed it.
- `ResolvesIndexSorting::resolveSorting()` gained an optional `$defaultDirection` (used when
  `?direction=` is absent) so the notes index defaults to `updated_at` descending.
- `Note.body` joins `RichTextFields`; `HtmlSanitizerTest` lists the field set and needed the entry.
- `LikeSearch::whereContains()` failed PHPStan on `NoteLinkType::queryFor()`'s
  `Builder<covariant Model>`. Its template is now the builder type (`TQuery of Builder<covariant Model>`),
  so callers keep their concrete type.
- The dev-DB test note "Fountain research" (id 3) is not deleted. The permission classifier
  refused a raw SQL delete on `database/database.sqlite`. Delete it in the app, or reseed.
