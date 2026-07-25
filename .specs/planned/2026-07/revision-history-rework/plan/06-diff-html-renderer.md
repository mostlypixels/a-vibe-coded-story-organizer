# Task 6 — `DiffHtmlRenderer`: structured diff → safe HTML

## Scope

`App\Services\Diff\DiffHtmlRenderer::render(array $diffBlocks, bool $inline = false): string`
— turns task 5's structure into the HTML string the compare view and the stored summaries
use.

Rules, all load-bearing:

* **every** text node goes through `e()`; nothing from the stored value is ever
  concatenated raw;
* the renderer emits only tags from its own constant `EMITTED_TAGS` — the block tags, the
  inline marks (`strong`, `em`, `u`, `s`, `code`, `a`), plus `ins`, `del`, `span`. A tag
  arriving from content is dropped, not passed through;
* attributes are re-emitted from parsed values, never copied as strings: `a[href]`
  (re-checked against `RichTextFields::purifierAllowedSchemes()`), `img[src|alt]`,
  `li[data-checked]`, `blockquote[data-callout-type]`;
* inserted / removed runs become `<ins>` / `<del>` carrying the class hooks task 12 styles,
  plus a visually-hidden label element;
* a formatting-changed block carries a data attribute naming the marks added/removed;
* `inline: true` (summary mode) strips author marks and emits only text + `<ins>`/`<del>`
  — a list row is a scan target, not a reading surface.

**Never purify the output.** The author allow-list would strip `<ins>`/`<del>`; the
guarantee comes from the renderer being the only producer.

Also in this task, the cheap invariant guard:
`tests/Unit/RichTextFieldsDiffTagsTest.php` asserting `RichTextFields::ALLOWED_TAGS`
contains `s` and does **not** contain `ins`/`del`.

## Depends on

Task 5.

## Key decisions already made

* **Produce, don't sanitise.** Order is purified content in → diff → wrap → render.
  Binding decision 7.
* `<del>`/`<ins>` are the diff layer's alone; the editor's strikethrough stays `<s>`.
  `<s>` means "no longer accurate", `<del>` means "removed" — different semantics, not
  synonyms.
* Output is safe to `{!! !!}`, the same contract the current `jfcherng` output has, for
  the same reason: the producer escapes.

## Consult

* `expanded/diffing.md` — *3. Render*, and *`<s>` vs `<del>`*.
* `app/Support/RichTextFields.php` — the allow-list to mirror (and to guard).
* `app/Services/HtmlSanitizer.php` — for what the input is guaranteed to be.

## Tests

Extend `tests/Unit/Services/VisualHtmlDifferTest.php` or add
`tests/Unit/Services/DiffHtmlRendererTest.php`:

* a text change renders `<ins>`/`<del>` around exactly the changed words;
* **security**: a stored value containing a literal `<del>injected</del>` (impossible via
  the sanitizer, possible via an import) cannot produce a change marker — assert the
  output escapes it;
* `<script>`, `&`, `"` in content are escaped in the output;
* the output contains no tag outside `EMITTED_TAGS` (regex assertion over the result);
* an `a[href="javascript:…"]` is emitted without the href (scheme re-check);
* `inline: true` output contains no `<strong>`/`<em>` but keeps `<ins>`/`<del>`;
* `tests/Unit/RichTextFieldsDiffTagsTest.php` — the allow-list guard described above.
