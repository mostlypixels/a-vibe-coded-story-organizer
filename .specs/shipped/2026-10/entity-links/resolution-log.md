# Entity links — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

An exception log: a task that went to plan gets no entry, because the diff and the task
file already record what was built. Bullets under the headings below, root cause first.

## Feedback & decisions

- Link form is `entity:<type>/<id>` in `href`, not `data-link`: Markdown scene text cannot hold attributes.
- Note-to-note links are text links only. The note-link pivot stays without `note`.
- The Manuskript research notes are converted by a one-off scratchpad script after shipping, plain-text `.md` path citations included. Not in the plan.
- `x-rich-text` takes a required `project` prop.
- "Linked from" shows on every target type.
- No dead-link check in the editor.
- Static site and EPUB render link text only.
- The picker is split into an endpoint task and an editor task.
- The public shared scene page shows link text only: a reader without an account cannot open the targets. Added to task 06.

## Deviations from the spec/plan

- The URL dialog accepts a valid `entity:` href for a link, but its refusal text still names only http(s). No new translation: the picker is the main path to insert a link.
- `EntityLinks::rewrite()` can only change an href or unwrap, so the missing span needs a new `EntityLinks::replaceAnchors()`. It touches HTML anchors only: in rendered HTML, Markdown-shaped text is literal (a code block).
- More components than `x-rich-text` take a required `project` prop: `x-diff`, `x-revision-version` and `x-story-chapter`. They pass it on to the resolver.
- `Scene::contentsExcerpt()` renders with `AuthorMarkdown::render()`, not `renderedContents`. It is plain text, so the link lookup is waste on the Story overview.
- The public shared scene page resolves links too. A reader without an account gets a link to a login page. Caller decision open: strip links there, as the exports do.
  - Resolved in exports: the shared page strips links (`Scene::linklessContents` and `EntityLinks::stripAnchors()` on the description).
- Exports use `EntityLinks::stripAnchors()` (HTML anchors only), not `strip()`. In rendered or stored HTML, Markdown-shaped text is literal, for example a code span. `strip()` would unwrap it.
- The static site strips only the `books/` reading pages. The `data/` layer keeps the links: the archive format says it is lossless, and the import needs them. The task test "no `entity:` in the output" holds for `books/` only.
- `HasEntityLinks` is on `Book` too, though a book has no link field. Without it, deleting one book leaves the rows that point at it. `EntityLinkIndex::fieldsOf()` returns `[]` for Book, so its save hook does nothing.
- `ProjectGraphImporter::linkMap()` now returns the `notes` map for `note`, not a `LogicException`. The Links phase needs it. The `noteLinkable()` check in `importNote()` still rejects a `note` pivot link.
- New `LinkTargetType::tryFromModel()`: `forget()` and `EntityLink::deleteFor()` also get a Project, which is a source but never a target.
- `x-wysiwyg` takes an optional `project` prop, not a required one: the new project page has no project. Without it, no "Link to…" button, slash item or dialog. `x-autosave-field` passes `$model->revisionProject()`; create pages pass their project.
- The shared search module (`resources/js/candidate-search.js`) also holds the arrow-key highlight (`highlighted`, `move()`). The note-link picker carries it unused.
- In the dialog, the search box comes before the type select. `x-modal` focuses the first focusable element, and the keyboard flow starts in the search box.
- The search box is a `role="combobox"` with `aria-controls`. `DisclosureToggleTest::assertTogglesControlExistingPanels()` now skips comboboxes: they are not disclosure toggles.
- The seed link is a `{scene}` placeholder in a note body. `SeedsNotes` swaps it for the href of the note's own scene. All three Melusine locales have it, on "The Saturday curse".
- The "Linked from" card is `x-linked-from-card` (class component, takes the target model). It sits on the read pages only, not the edit pages. A source with links in two fields shows once; a text that links to itself is not listed.
- `NotesCard::projectOf()` moved to `LinkTargetType::projectOf()`, now also used by the new card.

## Issues → resolutions

- Adding `note` to the enum made every `cases()` loop over note-link relations reach `Note`, which has no `noteRelation()` (it throws). Fix: `LinkTargetType::noteLinkableCases()` for those loops (`Note::linkGroups`, `StaticSiteExporter`, route `whereIn`, pickers, `Rule::enum(...)->only(...)`).
- `NoteLinkTarget::resolveKey()` would accept `?link=note:5` on the note create page, then `linkTo()` throws a 500. Fix: `NoteLinkTarget::resolve()` returns null for a non-note-linkable type. Test: `test_the_create_page_ignores_a_note_link_key`.
- `URI.AllowedSchemes` applies to every URI attribute, so adding `entity` also let `<img src="entity:...">` through. Fix: `EntityUriScheme` accepts only when the purifier context `CurrentAttr` is `href`. Test: `test_an_image_source_is_never_an_entity_link`.
- `DiffHtmlRenderer::renderLink()` checks `RichTextFields::ALLOWED_SCHEMES`, so revision diffs now render a raw `entity:` href. Not fixed here: rendering owns link resolution.
  - Resolved in rendering: `x-diff` runs the resolver on a visual diff, after `DiffHtmlRenderer`. `renderLink()` stays unchanged. The Markdown panel of `x-revision-version` resolves too. A source diff shows the link as typed.
- `StaticSiteExporter` reads `Scene::renderedContents`, which now holds app URLs. `EntityLinks::strip()` after that finds nothing. The export task must render with `AuthorMarkdown::render($scene->contents)` and strip that.
- `Scene::renderedContents` calls `project()` (chapter → act → book → project) only when the text has a link. On the Story overview in prose mode, that is a few lazy loads for each scene with a link. Accepted; eager-load if it shows.
- Eloquent fills `changes` on an update only, and `wasRecentlyCreated` stays true for later saves of the same instance. Fix: the save hook syncs when a link field `wasChanged()`, or when the row is new and its text holds a link. A second save of a just-created instance can re-sync; that is harmless.
- `syncSource()` reads `revisionProject()` only when there are rows to insert. For a scene it walks chapter → act → book → project, and autosave would pay it on every keystroke batch.
- The import phases before Links create rows with `create()`, so the save hook indexes links with archive ids. The Links phase `syncProject()` replaces them. A stalled import keeps these wrong rows until a resume. Accepted: the project is marked `import_unfinished`.
- `ProjectGraphImporter` takes `EntityLinkIndex` in its constructor. Tests that build it by hand (`ProjectImporterTest` mocks, `realImporter()`) pass it too.
- The Tiptap link mark is inclusive (`autolink: true`), so text typed after an inserted label joined the link. Fix: `pickLinkTarget()` runs `unsetMark('link')` after the insert. Test: the Vitest "inserts the label … the next text is not linked".
- The dialog opens under the pointer of the toolbar button. With `mouseenter`, the result under the still pointer took the highlight, and Enter picked the wrong target. Browser run only; Vitest missed it. Fix: `mousemove` sets the highlight.
- The dev database is behind five migrations, `entity_links` among them, so the dev server cannot show the card. The runtime check used a throwaway SQLite file (`DB_DATABASE`) and `php artisan serve` on another port. The dev database stays untouched.
- `npm run build` does not remove `public/hot` while the Docker Vite server runs, so `assets-state.sh` fails on it and on the pending migrations. Not caused by this task.
- The Docker Vite dev server was wedged (8 h CPU, `app.css` did not answer in 150 s). The browser check set `public/hot` aside to use the build, then put it back.
- Tiptap Markdown moves a mark inside a link: `**[x](entity:note/7)**` saves as `[**x**](entity:note/7)`. Same meaning; round-trip tests use the serializer order.
- A task 02 browser check edited dev note 3 ("Fountain research") and left its body empty. Earlier content, if any, is lost. Later tasks were told to use throwaway records only.
- A task 04 shell edit wiped nine model files. Seven came back from HEAD; Note and Scene were rebuilt by replaying the earlier task edits, then checked by diff and the full suite. Later tasks were told to edit with the Edit/Write tools only.
