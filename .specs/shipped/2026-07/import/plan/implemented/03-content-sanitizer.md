# Task 03 — `ContentSanitizer`

## Scope

Compose the existing `HtmlSanitizer`/`RichTextFields::ALLOWED_TAGS` allow-list with
an import-specific **reject-on-violation** policy, for the two kinds of content an
archive carries: raw HTML fragments (`description.html`/`notes.html`) and Markdown
(`contents.md`). **Does not** decide *where* in the pipeline this runs (that's task
05's `ProjectImporter::start()`) — this task only builds the checker and its tests
call it directly against string content.

* `app/Services/Import/ContentSanitizer.php`:
  * `assertHtmlAllowed(string $html): void` — throws `ImportValidationException`
    (from task 02) if `$html` contains any tag/attribute outside
    `RichTextFields::ALLOWED_TAGS`/its scheme allow-list. Reuse `HtmlSanitizer`'s
    parsing (e.g. run it through `HtmlSanitizer::clean()` and compare the result to
    the input — if cleaning changed anything, something was outside the allow-list,
    so reject) rather than re-implementing tag matching.
  * `assertMarkdownAllowed(string $markdown): void` — first runs the existing
    `App\Rules\ValidMarkdown` well-formedness check (it already throws/fails on
    unparseable input — surface that as `ImportValidationException` too), then
    renders the Markdown the same way the app does elsewhere (`Str::markdown()`) and
    runs the **rendered HTML** through `assertHtmlAllowed()` — this is what catches
    CommonMark's raw-HTML passthrough hole noted in `architecture.md`.

## Depends on

Task 02 (reuses `ImportValidationException`).

## Key decisions already made

* **Reject, never strip-and-continue.** This is the one place import is
  deliberately stricter than normal form submission (`SanitizesRichHtml`). Don't
  "helpfully" sanitize and import the cleaned version — throw.
* The allow-list itself (`RichTextFields::ALLOWED_TAGS`, `HtmlSanitizer`) is never
  duplicated or reimplemented — only the reject-vs-strip policy is new.

## Docs to consult

`architecture.md` → `ContentSanitizer` bullet; `open-questions.md` question 4
(resolved); `app/Support/RichTextFields.php`, `app/Services/HtmlSanitizer.php`,
`app/Rules/ValidMarkdown.php` (read these — don't guess their exact API).

## Tests

* `assertHtmlAllowed()` passes on HTML using only allowed tags/schemes; throws on
  `<script>`, `<iframe>`, an `on*=` handler, and a `javascript:`-scheme link.
* `assertMarkdownAllowed()` passes on plain CommonMark; throws on Markdown containing
  a raw disallowed HTML block/inline tag once rendered; throws on genuinely
  unparseable Markdown (via the existing `ValidMarkdown` behavior).
* A real `description.html`/`contents.md` pair produced by `StaticSiteExporter` for a
  normally-authored scene/entry passes both checks unchanged — confirms the policy
  doesn't have false positives on the app's own sanitized output.
