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

### Sanitize on read, for Markdown only

`Scene.contents` cannot be sanitized on write: the stored value must stay Markdown source so the editor can reload it. Markdown also carries raw HTML through, and `ValidMarkdown` only proves the source parses — it rejects nothing. So the *rendered* output is untrusted and is sanitized instead, by `App\Support\AuthorMarkdown::render()`.

Render author Markdown only through that method. `AuthorMarkdown::renderUnsanitized()` exists for the import allow-list check and the word counter; never echo its result.

### Allow-list

The allow-list contains supported structural and formatting markup, including headings through `h4`, links with HTTP(S), tables, task lists, images, and callout attributes.

Links also take the `entity:` scheme, the in-app link to a project entity:

- Form: `entity:<type>/<id>`. `<type>` is a `LinkTargetType` value. `<id>` is digits.
- The same form in HTML (`<a href="entity:scene/42">`) and in scene Markdown (`[text](entity:scene/42)`).
- `App\Support\Purifier\EntityUriScheme` validates it in both sanitizer profiles. It removes a link with an unknown type, a host, a query or a fragment, and any `entity:` image source.
- `App\Support\EntityLinks` finds, rewrites and strips these links.
- `App\Services\EntityLinkResolver` turns them into route URLs on read views: `x-rich-text`, `Scene::renderedContents`, and the revision views (`x-diff`, `x-revision-version`). A missing target, or an id from another project, becomes a struck `.entity-link-missing` span with the link text only.

Keep these surfaces aligned:

1. `RichTextFields` allow-list, `ALLOWED_SCHEMES` included.
2. `HtmlSanitizer` and `ContentSanitizer` (imports).
3. Tiptap extension configuration: link `protocols`, and `ENTITY_LINK_TYPES` in `resources/js/wysiwyg.js`, which the URL dialog checks.
4. Toolbar and slash-menu commands.
5. Sanitizer tests and round-trip tests.

### Two sanitizer profiles

`App\Enums\RichTextProfile` selects the allow-list `App\Services\HtmlSanitizer` applies. Not per-field: no caller needs a third variant.

- `Rich` permits the decorative classes below, on top of the structural allow-list.
- `Structural` permits none. Every seam that renders author Markdown passes it —
  `AuthorMarkdown::render()`, `ContentSanitizer::assertHtmlAllowed()`, and
  `EpubExporter::renderSceneContents()`. Scene text becomes EPUB body and is read aloud by
  TTS, so it stays structural; a new Markdown-rendering path must pass `Structural` too,
  proven by a test.

### Decorative classes

Rich HTML fields — descriptions, note bodies, codex — accept block alignment and named
text colour, closed class sets defined once in `App\Support\RichTextFields`:

- Alignment: `ALIGNMENTS` (`center`, `right`, `justify`), written as `rt-align-<name>` on
  an `ALIGNABLE_TAGS` block (`p`, `h1`–`h4`). `left` is the default and never becomes a
  class, so existing content needs no migration.
- Colour: `TEXT_COLORS` (`red`, `green`, `amber`, `blue`, `grey`), written as
  `rt-color-<name>` on a `span`. The class carries the colour name the author chose, not
  the theme token that renders it — see [Themes](../interface/themes.md) for that mapping.

No inline `style`, ever — both `@tiptap/extension-text-align` and the colour mark are
written locally to emit these classes instead of the stock inline styles, which the
sanitizer would strip anyway.

Three surfaces style these classes and must be kept in step when a class is added, renamed,
or removed:

1. `resources/css/app.css` — the application theme, one declaration per class using theme
   tokens, so it repaints with the active preset.
2. `resources/views/exports/epub/styles.css` — the EPUB stylesheet, fixed OKLCH values with
   a `prefers-color-scheme: dark` variant, because a reading app has no theme system.
3. Nothing else. A reader's own EPUB stylesheet can still win: single-class selectors, no
   `!important`.

Colour and alignment never carry meaning alone — nothing in the app reads a `rt-` class
back to drive behaviour.

### Render through components

- Use `x-rich-text` for complete rich HTML. Its `project` prop is required: it scopes the `entity:` links.
- Use `x-rich-text-excerpt` for escaped excerpts.
- Use `{!! !!}` only for content that a documented server path prepared.

## Markdown carve-out

`Scene.contents` remains Markdown.

- Validate it with `ValidMarkdown`.
- Render it through `Scene::renderedContents` for normal application consumers.
- `App\Support\AuthorMarkdown` holds the renderer choice and the sanitizing step. Use it
  for any author-written Markdown, never `Str::markdown()` directly.
- EPUB uses its own CommonMark converter and must not change the shared accessor. It
  registers the same strikethrough extension so both paths emit the same tag.

### Strikethrough renders as `<s>`

CommonMark renders `~~text~~` as `<del>`. This app reserves `<del>` and `<ins>` for
generated revision diffs, so they are not in the allow-list and the sanitizer strips
them. `App\Support\Markdown\StrikethroughSExtension` replaces the renderer with one
that emits `<s>` — the tag the editor already writes for the same author intent.

Widening the allow-list to admit `<del>` is the wrong fix: the diff viewer could then no
longer tell an author's strike from a deleted word.

## Editor

`x-wysiwyg` keeps a real textarea as the no-JavaScript submission control. `resources/js/wysiwyg.js` mounts Tiptap and synchronizes the textarea.

- HTML and Markdown modes share one extension builder.
- Image resize and table merge or split are disabled for Markdown because Markdown cannot preserve them.
- Underline, subscript, and superscript use raw inline HTML in Markdown.
- Callouts serialize as GitHub-style blockquotes.
- Link and image URLs accept HTTP(S) only. The URL dialog also accepts a valid `entity:` link.
- `sticky-toolbar` (default `false`) keeps the toolbar at the top of the nearest scroll box. Only the [Write page](editor.md) sets it.
- `autofocus` and `position-key` are opt-in too. `position-key` is a `localStorage` key that keeps scroll and cursor. The scroll box carries `data-position-scroll`.

### Link to…

The toolbar button and the slash item **Link to…** open a dialog that searches the project for a link target.

- `x-wysiwyg` takes an optional `project` prop. With it, the editor gets the `projects.link-targets` URL and shows the dialog. Without it (the new project page), the editor offers web links only.
- `x-autosave-field` passes `revisionProject()` of its model. Create pages pass their project.
- The dialog has a search box, a type select (every `LinkTargetType`) and a result list. Arrow keys move in the results, Enter picks, Escape closes.
- A pick on selected text sets `href="entity:<type>/<id>"`. With no selection, the label of the target becomes the link text.
- `resources/js/candidate-search.js` holds the search, debounce and response order. The note-link picker (`resources/js/notes/linkPicker.js`) uses it too.
- In the editor, an `entity:` link has its own style (`.tiptap a[href^='entity:']`). A click does not open it.
- No dead-link check in the editor. Read views show a missing target as `.entity-link-missing`.
- A codex entry matches by name and by alias (`LinkTargetType::candidates()`). The result label stays the entry name. Every picker gets this.

> [!WARNING]
> The dialog state is reactive Alpine data, but the Tiptap `Editor` stays in the closure of `registerWysiwyg`. An Alpine proxy around the editor breaks ProseMirror state.

### Linked from

Every target read page (book, act, chapter, scene, event, plotline, codex entry, note) shows a **Linked from** card next to the Notes card. It uses `x-linked-from-card` (`App\View\Components\LinkedFromCard`).

- It lists the texts that link to the entity, from the `entity_links` index. Each row shows the source title, its type, its link fields (Description, Prose, Text) and a link to the source.
- A source with links in two fields shows once.
- The card is hidden when the list is empty.
- It runs one query for the rows and one for each source type, whatever the number of sources.
- It lists sources of the same project only. A link can name a target id of another project. That target must not list the source.
- A text that links to its own entity is not listed.

### Smart punctuation

`@tiptap/extension-typography` converts as the writer types, in both formats:

| Typed | Becomes |
| --- | --- |
| `--` | en dash |
| `---` | em dash |
| `...` | ellipsis |
| `"` `'` | curly quotes |

It follows **CommonMark's** dash convention, not Typography's own — Typography's `emDash`
rule fires on two hyphens. It is overridden to write an en dash, and the local
`EmDashFromThreeHyphens` rule upgrades it when a third arrives. The convention has to match
`CanonicalPunctuation`'s rules, or a hyphen pair typed today and one imported yesterday end
up as different characters in the same book.

The other 16 Typography rules are disabled by name — arrows, fractions, `(c)`, guillemets
and the rest are wrong in a novel. Naming them individually means a Tiptap upgrade that
adds a rule cannot switch it on for us.

> [!NOTE]
> Input rules fire on keystrokes only. A paste runs the separate
> `NormalizePastedPunctuation` plugin instead. Imported text is normalized on the way in by
> `App\Services\Import\ContentSanitizer`, so it reaches the editor already canonical. See
> *Canonical punctuation* below for how the three paths stay in agreement.

### Canonical punctuation

`tests/Fixtures/punctuation.json` is the single definition of the app's punctuation
convention: which typewriter input becomes which typographic character. Three
implementations assert against it and must agree:

- `App\Support\CanonicalPunctuation` — import, one text run at a time.
- The editor's Typography input rules (above) — as the writer types.
- `NormalizePastedPunctuation` in `resources/js/wysiwyg.js` — on paste.

Import reaches the convention through `App\Services\Import\ContentSanitizer`, which
normalizes a fragment's punctuation only after the allow-list check passes.
`ManuskriptImporter` gets this for free by calling the sanitizer; it holds no
punctuation code of its own.

A case may not live in only one of the three implementations' tests — add it to the
fixture first.

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
| Author Markdown rendering | `app/Support/AuthorMarkdown.php`, `app/Support/Markdown/` |
| Blade components | `resources/views/components/rich-text*.blade.php` |
| Editor | `resources/views/components/wysiwyg.blade.php`, `resources/js/wysiwyg.js` |

## Related documentation

- [Revisions](revisions.md)
- [Writing progress](writing-progress.md)
- [EPUB](../export-import/epub.md)
