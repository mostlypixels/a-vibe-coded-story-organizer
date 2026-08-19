# Rich text

[Documentation](../README.md) › [Features](README.md) › Rich text

## Field taxonomy

`App\Support\RichTextFields` is the source of truth for rich HTML fields and their allow-list. `App\Support\AutosavableFields` assigns each autosaved field a `FieldKind`.

- `Rich` stores sanitized HTML.
- `Markdown` stores CommonMark source. `Scene.contents` is the main Markdown field.
- `Plain` stores plain text.

## Security model

### Sanitize on write

`App\Services\HtmlSanitizer` uses HTMLPurifier. Model mutators and `SanitizeHtml` apply it before persistence, including writes without model events.

### Allow-list

The allow-list contains supported structural and formatting markup, including headings through `h4`, links with HTTP(S), tables, task lists, images, and callout attributes.

Keep these surfaces aligned:

1. `RichTextFields` allow-list.
2. Tiptap extension configuration.
3. Toolbar and slash-menu commands.
4. Sanitizer tests and round-trip tests.

### Render through components

- Use `x-rich-text` for complete rich HTML.
- Use `x-rich-text-excerpt` for escaped excerpts.
- Use `{!! !!}` only for content that a documented server path prepared.

## Markdown carve-out

`Scene.contents` remains Markdown.

- Validate it with `ValidMarkdown`.
- Render it through `Scene::renderedContents` for normal application consumers.
- EPUB uses its own SmartPunct path and must not change the shared accessor.

## Editor

`x-wysiwyg` keeps a real textarea as the no-JavaScript submission control. `resources/js/wysiwyg.js` mounts Tiptap and synchronizes the textarea.

- HTML and Markdown modes share one extension builder.
- Image resize and table merge or split are disabled for Markdown because Markdown cannot preserve them.
- Underline, subscript, and superscript use raw inline HTML in Markdown.
- Callouts serialize as GitHub-style blockquotes.
- Link and image URLs accept HTTP(S) only.

> [!CAUTION]
> Keep the Tiptap `Editor` in a closure. Alpine proxies reactive values, and a proxied ProseMirror editor does not work reliably.

## Loss warnings

`resources/js/wysiwyg/fallbackChecks.js` detects structures that Markdown cannot preserve:

1. merged table cells;
2. resized images;
3. unknown raw-HTML wrapper tags.

The checks use document structure, not text diffs, so harmless Markdown serialization changes do not trigger them.

## Plain-text conversion

`App\Support\RichText::toPlainText()` inserts breaks after block elements and `<br>` before stripping tags. This prevents adjacent blocks from joining words. Inline elements do not create breaks.

## Where things live

| Concern | Location |
| --- | --- |
| Field taxonomy and allow-list | `app/Support/RichTextFields.php` |
| Sanitizer | `app/Services/HtmlSanitizer.php` |
| Model mutators | `app/Models/Concerns/SanitizesRichHtml.php` |
| Request rule | `app/Rules/SanitizeHtml.php` |
| Plain text and XHTML conversion | `app/Support/RichText.php` |
| Blade components | `resources/views/components/rich-text*.blade.php` |
| Editor | `resources/views/components/wysiwyg.blade.php`, `resources/js/wysiwyg.js` |

## Related documentation

- [Revisions](revisions.md)
- [Writing progress](writing-progress.md)
- [EPUB](../export-import/epub.md)
