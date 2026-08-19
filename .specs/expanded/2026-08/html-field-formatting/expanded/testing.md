# Testing

## PHP

`tests/Unit/HtmlSanitizerTest.php`

- A `rt-align-center` class on `<p>` and `<h2>` survives the `Rich` profile.
- A `rt-color-blue` class on `<span>` survives the `Rich` profile.
- `rt-color-chartreuse` (not in the registry) is stripped; the element and its text remain.
- `class="prose"` — an in-app utility class — is stripped.
- `style="color: red"` and `style="text-align: center"` are stripped.
- Every class returned by `RichTextFields::decorativeClasses()` survives. Loop the registry
  so adding a colour cannot silently escape the test.
- The `Structural` profile strips all of the above.

`tests/Unit/Rules/…` and `tests/Feature/HtmlSanitizationTest.php`

- Posting `<span class="rt-color-red">x</span>` to a codex `description` persists it.
- Posting the same into `Scene.contents` does not survive `AuthorMarkdown::render()`.
- The same class in a scene body does not survive `EpubExporter::renderSceneContents()`,
  while a codex description keeps it — the one assertion that pins "appendices only".

`tests/Unit/Support/WysiwygToolbarTest.php` (new if absent)

- `alignment()` and `textColor()` are non-empty for HTML, empty for Markdown.
- Their item values are drawn from `ALIGNMENTS` / `TEXT_COLORS`, not literals.

`tests/Feature/WysiwygFormTest.php`

- A Markdown-format field's rendered toolbar contains no `setTextAlign` and no
  `setTextColor`. Scene contents is the case that matters.

Import (`tests/Feature/Import…`)

- An archive whose scene Markdown contains `<span class="rt-color-red">` is rejected.
- An archive whose codex description contains it is accepted.

EPUB (`tests/Feature/…EpubExport…`)

- A codex entry description with both classes reaches `appendix-entry-*.xhtml` intact.
- `styles.css` in the package defines a rule for every `decorativeClasses()` entry.

Theme (`tests/Unit/ThemePresetTest.php` neighbourhood)

- Every palette value clears `ColorContrast::TEXT_FLOOR` against the light and dark reading
  surfaces. Reuse the existing preset-pair assertion style.

## JavaScript (`resources/js/wysiwyg.test.js`)

- HTML editor: `setTextAlign('center')` then `getHTML()` yields `class="rt-align-center"`
  and no `style`.
- HTML editor: colour mark round-trips through `getHTML()` → new editor → `getHTML()`.
- Markdown editor: neither extension is registered
  (`extensionManager.extensions.find(...)` is undefined), matching the `imageOptions`
  helper already in the file.
- `buildSlashItems('markdown')` contains no align or colour entry;
  `buildSlashItems('html')` contains one per registry value.
- Parity: the slash titles for these commands equal the toolbar labels.
- Registry parity: the JS `ALIGNMENTS` / `TEXT_COLORS` literals match the PHP constants,
  read from source in the manner of `css-source-smoke.test.js`.

## Edge cases

- Nested colour spans — inner wins; assert the outer is not duplicated on serialization.
- Alignment applied while a heading is toggled to paragraph: attribute must not leak to a
  node type outside `ALIGNABLE_TAGS`.
- Paste of styled HTML from a word processor: the class survives only if it is in the
  registry; everything else is dropped at write time, not at paste time.
- A revision diff of a colour-only change (see `open-questions.md` for the decision).
