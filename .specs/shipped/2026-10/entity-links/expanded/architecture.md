# Entity links — architecture

## Components

| Concern | Location |
| --- | --- |
| Parse and rewrite `entity:` refs in HTML or Markdown | `app/Support/EntityLinks.php` (pure, no database) |
| Resolve refs to URLs, mark missing ones | `app/Services/EntityLinkResolver.php` |
| Keep `entity_links` in step | `app/Services/EntityLinkIndex.php` |
| Save and delete hooks | `app/Models/Concerns/HasEntityLinks.php` |
| Sanitizer scheme | `app/Support/Purifier/EntityUriScheme.php` (an `HTMLPurifier_URIScheme`) |
| Picker endpoint | reuse `NoteLinkController::candidates` logic, moved to a project-level route |

## Sanitizer

- Register `entity` in `HtmlSanitizer` for **both** profiles. Scene Markdown renders through `Structural`.
- The scheme class accepts only `<LinkTargetType value>/<digits>`. It rejects a query, a fragment, and a host.
- Add `entity` to `RichTextFields::ALLOWED_SCHEMES` and to the Tiptap link `protocols` in `resources/js/wysiwyg.js`. Keep these surfaces aligned, as `rich-text.md` → Allow-list says.
- `ContentSanitizer::assertHtmlAllowed()` must accept the scheme, or an import with links fails.

## Rendering

- `EntityLinkResolver::render(string $html, Project $project): string`
  - Collect the refs. Run one `LinkTargetType::queryFor($project)->whereKey($ids)` per type.
  - A found ref becomes its route URL. A missing ref becomes `<span class="entity-link-missing">text</span>`.
- Call sites:
  - `x-rich-text` gets a required `project` prop. Every caller passes it.
  - `Scene::renderedContents`, after `AuthorMarkdown::render()`.
- `x-rich-text-excerpt` and `RichText::toPlainText()` keep only the link text. They need no change; check this in a test.
- EPUB (`EpubExporter::renderSceneContents()` and the description renderers) and `StaticSiteExporter` call `EntityLinks::strip()`. It keeps the text and drops the `<a>`.

## Index sync

- `HasEntityLinks` on every model in `RichTextFields::FIELDS` and on `Scene`.
  - `saved`: if a link field `wasChanged()`, call `EntityLinkIndex::sync($model)`.
  - `deleted`: `EntityLinkIndex::forget($model)` removes rows where the model is source or target.
- A hook fits here, unlike the codex matcher: the index comes from one row's own fields and never needs a project-wide rescan.
- `FieldAutosaver`, `SceneSaver` and `CodexEntrySaver` all call `save()`. They need no extra call.
- The duplicators copy the text, and the copy's save indexes it. Links keep pointing at the original target, which is correct.
- A revision revert saves the model, so the index follows.

## Import

- Add `ImportPhase::Links` after `Notes`. Every ID map exists by then, and `id_maps` is already persisted, so resume works.
- The phase walks every rich field and every `Scene.contents` of the new project. It rewrites each `entity:<type>/<old>` with `$idMaps`. An old id with no map entry becomes plain text.
- Add a `notes` ID map. Today the Notes phase does not record note ids.
- Then sync the index for the project. One transaction for the phase.
- Export writes the text as stored. It needs no change.
- Document the `entity:` form in `documentation/export-import/archive-format.md`.

## Authorization

- The picker endpoint authorizes `view` on the project, as `NoteLinkController::candidates` does.
- The resolver scopes every lookup with `queryFor($project)`. A link is never trusted across projects.
- No new write endpoint: links are written through the existing field saves.

## Manuskript notes

The `manuskript-import` branch never merges, and it did not create note 200. Converting these notes is a one-off task, not shipped code. See open questions.
