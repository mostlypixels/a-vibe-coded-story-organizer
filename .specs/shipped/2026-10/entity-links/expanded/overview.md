# Entity links — overview

## Scope

| Field | Format | Links allowed |
| --- | --- | --- |
| Every field in `RichTextFields::FIELDS` (descriptions, `Note.body`) | HTML | yes |
| `Scene.contents` | Markdown | yes |
| Plain-text fields, titles | plain | no |

Targets: every `NoteLinkType` case (book, act, chapter, scene, event, plotline, codex), plus `note`. Note 200 cites other research notes (`society-households-and-material-life.md`), so note-to-note links are needed.

## Decision: a URI scheme, not `data-link`

A link is a normal `<a>` with a custom scheme:

```
HTML:      <a href="entity:scene/42">The duchess ball</a>
Markdown:  [The duchess ball](entity:scene/42)
```

- `Scene.contents` is Markdown. Markdown has no syntax for attributes, so `data-link` would force raw HTML into scene text.
- One form for both formats means one parser, one sanitizer rule, and one import rewrite.
- `<id>` is the database id. In an archive, it is the archive id, the same as every other reference ([Stable identifiers](../../../../../documentation/export-import/archive-format.md#stable-identifiers)).

## User stories

- From any editor, I insert a link to a scene, chapter, codex entry or note that I pick by name.
- In the read view, a link opens the target.
- On a target, I see which texts link to it.
- After export and import, the links open the new copies.
- A link to a deleted target shows its text, marked as missing. It does not open a 404 page.

## Acceptance criteria

- `entity:<type>/<id>` survives both sanitizer profiles. Any other `entity:` form is removed.
- Read views render the route URL. The resolver uses one query per type in the text, scoped to the project.
- A link to another project's entity renders as missing. A guessed id must not leak a title or a URL.
- An import rewrites every link to the new id. An id with no match in the ID maps becomes plain text and is not an import error.
- EPUB and static site output keep the link text and drop the link.
- The backlinks table equals "the links in the saved text" after every save, delete and import.

## Non-goals

- Link text that follows a rename. The text stays what the writer typed.
- Line or paragraph anchors. `.md:52` keeps only the scene.
- A check in the editor that marks dead links while the writer edits.
