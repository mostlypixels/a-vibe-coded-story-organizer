# Architecture

No migration, no route, no policy change. The feature is a widening of one allow-list plus
a gated toolbar cluster and two editor extensions.

## The registry (`app/Support/RichTextFields.php`)

Add the closed value sets and derive everything else from them.

```php
/** Block alignment. `left` is the default and is never written as a class. */
public const ALIGNMENTS = ['center', 'right', 'justify'];

/** Named text colours. Values resolve in CSS, never here. */
public const TEXT_COLORS = ['red', 'orange', 'green', 'blue', 'purple', 'grey'];

/** Tags that may carry an alignment class. */
public const ALIGNABLE_TAGS = ['p', 'h1', 'h2', 'h3', 'h4'];

/** @return list<string> Every class the sanitizer permits, e.g. `rt-align-center`. */
public static function decorativeClasses(): array;
```

- `ALLOWED_ATTRIBUTES` gains `class` for `ALIGNABLE_TAGS` and for `span`.
- `class` is a HTML4 attribute, so it needs no `addAttribute()` call and no
  `HTML.DefinitionRev` bump — unlike the `data-*` names.
- `span` is already an allowed tag (task lists). Colour reuses it.

## Two sanitizer profiles (`app/Services/HtmlSanitizer.php`)

The class list must not reach the Markdown path. Since #117 there is one obvious place to
stop it: `App\Support\AuthorMarkdown::render()` sanitizes every author-Markdown render
with the *same* allow-list the rich fields use. Widen that allow-list and a raw
`<span class="rt-color-red">` typed into `Scene.contents` renders coloured — the leak the
spec forbids. So the profile split is load-bearing, not tidiness.

- New enum `App\Enums\RichTextProfile { Rich, Structural }`.
- `HtmlSanitizer` holds two lazily built `HTMLPurifier` instances, one per case; the
  singleton binding in `AppServiceProvider` is unchanged.
- `clean(string $html, RichTextProfile $profile = RichTextProfile::Rich): string` — the
  default keeps every existing caller working.
- `Rich` sets `Attr.AllowedClasses` to `RichTextFields::decorativeClasses()`.
  `Structural` sets it to `[]`, which strips every class.
- `AuthorMarkdown::render()` passes `Structural`. That one line is the whole Markdown lock
  for display, the static site, and the story overview.
- `ContentSanitizer::assertMarkdownAllowed()` also passes `Structural`: it cleans
  `AuthorMarkdown::renderUnsanitized()` output and compares, so an import carrying the
  class must still be rejected rather than silently accepted.
- `EpubExporter::renderSceneContents()` passes `Structural` too — scene bodies are
  narrative. Codex descriptions go through `RichText::toXhtmlFragment()` and stay `Rich`,
  which is what puts decoration in appendices and nowhere else.

Rejected: one profile plus a `ValidMarkdown` regex against class names. It duplicates the
allow-list in a second grammar and drifts.

## Toolbar data (`app/Support/WysiwygToolbar.php`)

Two new methods, both returning `[]` when `$this->markdown`:

- `alignment()` — one item per `ALIGNMENTS` entry plus a `left` reset item; command
  `setTextAlign`, args `['align' => …]`, active `['textAlign', ['align' => …]]`.
- `textColor()` — one item per `TEXT_COLORS` entry plus a `Remove colour` item; command
  `setTextColor` / `unsetTextColor`, active `['textColor', ['color' => …]]`.

Returning `[]` rather than omitting the call keeps `wysiwyg.blade.php` free of a second
conditional; the dropdown component skips an empty item list. Add that early return to
`x-wysiwyg.toolbar-dropdown` — it currently assumes a non-empty array.

## Editor (`resources/js/wysiwyg.js`)

`buildExtensions(format)` already computes `isMarkdown`. Push the two extensions only when
`!isMarkdown`, and gate the slash items the same way.

- **No new npm dependency.** `@tiptap/extension-text-align` and the `TextStyle` colour mark
  both emit inline `style`, which the sanitizer strips. Write both as local extensions in
  the existing file, beside `Callout`.
- `TextAlign`: an `Extension` adding a `textAlign` attribute to
  `RichTextFields::ALIGNABLE_TAGS` node types, `parseHTML` reading `rt-align-*` off the
  class, `renderHTML` writing it back. Default `left` renders no class.
- `TextColor`: a `Mark` named `textColor` rendering `['span', { class: 'rt-color-' + name }, 0]`,
  parsing `span[class*="rt-color-"]`, rejecting any name outside the list.
- `buildSlashItems(format, …)` currently ignores `format`. Give it the gate: append the
  align and colour items only for `html`. The parity invariant is that the slash list and
  the toolbar list are gated by the same boolean — assert it in a test, not by comment.

## Value sets in two languages

`CALLOUT_TYPES` already lives in both PHP and JS with a "keep in step" comment. Follow the
same pattern for `ALIGNMENTS` and `TEXT_COLORS`, and add a test that reads the JS source
and compares the literals — `resources/js/css-source-smoke.test.js` shows the precedent for
a JS test that greps project source.

## Downstream consumers

| Consumer | Effect |
| --- | --- |
| `RichText::toPlainText()` | None. `span` is inline; `p`/`h*` already break. |
| `RichText::toXhtmlFragment()` | None. `class` is well-formed XHTML. |
| `Diff\HtmlTokenizer` | Alignment and colour are invisible to the diff — a colour-only change reads as "no change". Decide in `open-questions.md`. |
| `StaticSiteExporter` | Uses `toPlainText()` for the project description; unaffected. |
| `EpubExporter` | Ships `RichText::toXhtmlFragment($entry->description)` into `appendix-entry.blade.php` unchanged. The classes arrive; only CSS is missing. |
