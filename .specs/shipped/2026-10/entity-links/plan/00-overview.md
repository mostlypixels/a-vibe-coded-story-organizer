# Entity links — plan overview

## Execution order

1. `01-link-target-type` — rename `NoteLinkType` → `LinkTargetType`, add `note`.
2. `02-parse-and-sanitize` — `EntityLinks` parser and the `entity:` scheme on every sanitizer and editor surface.
3. `03-rendering` — `EntityLinkResolver`, required `project` prop on `x-rich-text`, scene Markdown, missing-link style.
4. `04-backlinks-index` — `entity_links` table, index service, save and delete hooks, cascades, seed link.
5. `05-import` — `Links` import phase, `notes` ID map, archive-format docs.
6. `06-exports` — EPUB and static site keep link text only.
7. `07-picker-endpoint` — project-level candidate search over every target type.
8. `08-editor-dialog` — "Link to…" dialog in the editor.
9. `09-linked-from-card` — "Linked from" card on every target page.

## Binding decisions

- Link form: `entity:<type>/<id>` in `href`, in HTML and in Markdown. No `data-link`.
- `<type>` is a `LinkTargetType` value. `<id>` is the database id; in an archive, the archive id.
- `note` is a text-link target only. The note-link pivot picker hides it.
- `x-rich-text` takes a required `project` prop.
- No dead-link check in the editor.
- EPUB and static site render link text only.
- "Linked from" shows on every target type, hidden when empty.
- Converting the Manuskript research notes (note 200 and the others) is a one-off scratchpad script after shipping. It is not in this plan.

## Invariants

- Every lookup goes through `LinkTargetType::queryFor($project)`. A link never resolves across projects, and a missing target leaks no title or URL.
- Rendering runs one query per target type in the text, whatever the number of links.
- `entity_links` equals the links in the saved text. Each sync replaces all rows of one (source, field). Delete hooks and the Book, Act and Chapter cascades remove rows for sources and targets.
- `LinkTargetType` values never change. Archives and `notables` rows depend on them.
- Keep the allow-list surfaces aligned: `RichTextFields`, `HtmlSanitizer`, `ContentSanitizer`, Tiptap `protocols` (see `documentation/features/rich-text.md` → Allow-list).
