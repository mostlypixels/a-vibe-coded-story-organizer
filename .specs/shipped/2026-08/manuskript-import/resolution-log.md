# Manuskript Import — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

* Character field headings use `e(ucfirst($key))`, not title case. `ManuskriptFile::header()`
  lowercases every key on parse (task 01, binding), so the original source casing (`Full Summary`)
  is unrecoverable; `ucfirst` alone reproduces the sentence-case style the spec's own example uses
  (`Last name`), without guessing at word boundaries `ucwords` would get wrong for a key like `id`.
* Added `characters/03-shopkeeper.txt` to the fixture (`Name: Aline & Fils <the shopkeeper>`, every
  other field `?`) to cover the plan's "name containing `&` or `<` is escaped, not stripped" case —
  `CodexEntry.name` is plain text, never run through the rich-HTML sanitizer, so the test proves the
  importer does not pre-escape or mangle it.

* Disallowed HTML in a scene body is stripped and replaced by `[INVALID CONTENT REMOVED]` on its own
  line, then counted — not an abort (the source is the writer's own directory, and one stray line
  must not block a migration) and not a silent strip.
* `ID`, `Color`, `Importance` and `POV` are Manuskript bookkeeping and never reach a character's
  description; `Full Summary` and `Notes` do.
* Multi-line field values become one `<p>` per blank-line block rather than a single `<div>` — the
  editor would rewrite a `<div>` to paragraphs on the first save anyway.
* The real source tree stores a literal `-` (not `?`) in some character fields as its
  "not filled in" placeholder. Only `''` and `?` are skipped, so a `-` field is a real value and
  becomes an `<h3>` heading with a `-` body. This is correct — do not extend the skip set to `-`;
  the writer typed it and other fields on the same character are genuinely `-`.
* The act is `"Act 1"` with an `--act=` override.
* Empty chapters and empty scenes are imported and counted: they are outline structure the writer
  planned.
* Scene status stays the `scenes.status` column default (`draft`); `status.txt` is user-defined and
  unmappable.
* The fixture's three chapter directories are `00-first-chapter`, `5-empty-chapter`,
  `10-second-chapter` — the unpadded `5-` is deliberate. Two zero-padded prefixes alone (`00`/`10`)
  sort identically as strings and as numbers; only a differently-padded third prefix makes a string
  sort ("00", "10", "5") disagree with a numeric one ("00", "5", "10").
* Gate/name failures throw a new `App\Exceptions\ManuskriptImportException`, not the existing
  `ImportValidationException` — that class's docblock ties it to the HTTP archive-import feature
  (`ImportController` redirect-back), a different domain. `ManuskriptImportCommand` catches
  `RuntimeException` broadly, so `ManuskriptFile`'s own "file not found" also surfaces as a clean
  `FAILURE` without a third exception type.

## Deviations from the spec/plan

* `spec.md` says scene Markdown is converted to HTML — it is not. `Scene.contents` is the app's
  Markdown carve-out (`App\Support\RichTextFields`).
* `spec.md` says the import produces one baseline revision per record — it produces none. Baselines
  are seeded lazily on the first edit (`RevisionRecorder::ensureBaseline()`).

## Issues → resolutions

* The fixture's tainted scene used a `<script>` tag for the disallowed-HTML case. GFM's
  `DisallowedRawHtmlExtension` escapes `script`/`style`/`iframe`/`noembed` to inert text at render
  time, so that content never fails `ContentSanitizer::assertMarkdownAllowed()` — it renders safe
  and is left alone. Fixed by swapping the fixture to `<object data="evil.swf"></object>`, a tag
  outside that short escaped list, which passes through raw and is caught.
* The real tree carries a stray `.grazie.en.yaml` (a JetBrains grammar-checker file) inside a
  chapter directory. The importer skipped and counted it through the existing "non-scene entry in
  chapter" branch — correct, no code change. Root cause of the gap: the fixture only covered a
  loose file directly under `outline/`, never a stray non-`.md` file inside a chapter directory, so
  that branch was untested. Added `outline/00-first-chapter/.grazie.en.yaml` to the fixture and a
  test asserting the import reports it as skipped.
