# Architecture

## Routes

Shallow, like codex and plotlines.

| Route | Action |
|---|---|
| `projects.notes` resource (`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`), shallow | `NoteController` |
| `POST /projects/{project}/note-categories` | `NoteCategoryController@store` |
| `PATCH /note-categories/{category}` | rename or move (`name`, `parent_id`) |
| `DELETE /note-categories/{category}` | delete, children move up |
| `POST /notes/{note}/links` | `NoteLinkController@store` (`type`, `id`) |
| `DELETE /notes/{note}/links/{type}/{id}` | `NoteLinkController@destroy`; `{type}` constrained by `NoteLinkType` |
| `GET /notes/{note}/link-candidates?type=&q=` | JSON for the picker, first 20 by name |
| `GET /projects/{project}/notes/candidates?q=` (`notes.candidates`) | JSON notes by title, first 20, for "Link a note" |

`notes.create?link=scene:12` pre-fills one link. The store request validates it like any link.

## Authorization

- Note, category: `$this->authorize('update', $note->project)`.
- Link store and destroy: authorize the note's project, then resolve the target through
  `NoteLinkType::queryFor($project)`. A target outside the project is a validation error, so a
  guessed id cannot link across projects.
- Every Form Request mirrors the check in `authorize()`.

## Where logic lives

| Concern | Location |
|---|---|
| Category delete with move-up, in one transaction | `App\Services\NoteCategoryDeleter` |
| Parent rule: same project, no cycle, depth ≤ 3 | `App\Rules\ValidNoteCategoryParent` |
| Link target rule | `App\Rules\NoteLinkTarget` |
| Starter names and creation | `App\Services\StarterNoteCategories` |
| Heading outline and anchors for the read view | `App\Support\NoteOutline` |
| Category tree for the sidebar and the select | `App\Support\NoteCategoryTree` (one query, built in PHP) |

`NoteOutline` adds `id`s to `h1`–`h4` at render time. Stored HTML keeps no `id`, so the
sanitizer allow-list does not change. Output goes through `x-rich-text` after the anchors are
added — the same path as other server-prepared HTML.

## Index query (`NoteController@index`)

Filtering stays in the controller, as the project rules ask.

- `category` — notes directly in that category. `none` — uncategorized.
- `linked` — a `NoteLinkType` key: notes with at least one link of that type.
- `book` — notes linked to the book or to an act, chapter or scene inside it.
- `search` — `LikeSearch::whereContains` on `title`. Full-text body search is project search's job.
- Sort `title` or `updated_at` (`ResolvesIndexSorting`). Paginate with `PageSize`.
- Eager-load `category` and a link count.

## Revisions and autosave

- `AutosavableFields::REGISTRY` gains `'note' => [Note::class, ['body' => FieldKind::Rich], 'notes.edit']`.
- `RichTextFields` gains `Note::class => ['body']`.
- `config/revisions.php` caps: `'note.body' => 500_000`. The Gardenia synopsis is 56 KB as
  Markdown and grows as HTML; the 100 000 default is too small.
- Title has no autosave. Last save wins, like other names.
- Remove `scene.notes` from both registries.

## Search

- `SearchDomain::Notes` (route key `notes`, edit route `notes.edit`, view route `notes.show`).
  `carriesBook()` is false.
- `ProjectSearch` gains `NOTE_FIELDS = ['title' => 'Title', 'body' => 'Body']`. Drop `notes` from
  `SCENE_FIELDS`.
- `SearchResults` gains a `notes` column.

## Export and import

- `StaticSiteExporter::addNotes()` writes `data/notes/categories.json` (id, parent_id, name),
  `data/notes/<id>-slug/note.json` (id, category_id, title, `links: [{type, id}]`) and
  `body.html` through `addFieldFile()`. Link `type` is the `NoteLinkType` key, never a class name.
- Scene `notes_file` is no longer written.
- `ProjectGraphImporter::importNotes()` runs last, after story, timeline and codex, so every
  link remaps through `$idMaps`. Categories import parents first. A link whose target is not
  in `$idMaps` is an import error, like any broken cross-reference.
- An old archive's scene `notes_file` becomes a note linked to the scene, with the migration's
  title rule. The shared rule lives in one place: `Note::titleForSceneNotes(string $sceneName)`.
- Manifest version: see `open-questions.md`.
- Notes never reach the `books/` reading layer, EPUB, or the shared-scene page.

## Other `scenes.notes` call sites to change

`SceneDuplicator` (copy links, as for other references), `SharedSceneController` (comment
only), `EntitySnapshot` (comment only), `StoreSceneRequest`, `UpdateSceneRequest`,
`resources/views/scenes/{create,edit,show}.blade.php`, `resources/js/autosave/field.test.js`,
`resources/js/wysiwyg.test.js`, `database/seeders/data/extract-long-novel.php`.

> [!WARNING]
> The unmerged `manuskript-import` branch touches the same files (`ProjectGraphImporter`,
> `ProjectSearch`, `SceneDuplicator`). Expect a rebase conflict. It does not write
> `scenes.notes` itself.

## Documentation to update

- New `documentation/features/notes.md`; link it from `features/README.md` and the
  architecture feature table.
- `architecture/README.md` domain model: `Project → Note, NoteCategory`.
- `export-import/archive-format.md`: the `data/notes/` branch.
- `features/rich-text.md`: replace `Scene.notes` in the decorative-classes list.
- `architecture/glossary.md`: Note, Note category, Link.
