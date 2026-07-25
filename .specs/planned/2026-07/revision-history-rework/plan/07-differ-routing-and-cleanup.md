# Task 7 — Route `RevisionDiffer` by `FieldKind`, delete the plain-text projection

## Scope

`App\Services\RevisionDiffer::diff(FieldKind $kind, ?string $old, ?string $new)` keeps its
signature and becomes a router:

* `FieldKind::Rich` → `VisualHtmlDiffer` + `DiffHtmlRenderer` (tasks 5–6);
* `FieldKind::Markdown` / `Plain` → today's `jfcherng/php-diff` `SideBySide` word-level
  source diff, unchanged.

Remove the Rich plain-text projection: `RichText::toPlainText()` is no longer called from
the differ, and `RevisionDiffResult::formattingChangedOnly()` plus its
`$result->formattingChangedOnly` branch in `resources/views/revisions/compare.blade.php`
are **deleted** — with a real visual diff, that state no longer exists.

`RevisionDiffResult` keeps `html`, and gains whatever task 5's structure needs for the
compare view (e.g. the hunk count). Keep it a readonly value object.

The existing compare page must still render after this task: it is rewritten in task 14,
but the suite has to be green here.

## Depends on

Tasks 5, 6.

## Key decisions already made

* **`Scene.contents` stays a source diff.** The markup is what the writer types, and it is
  the field she cares about most. Never route it through the sanitizer or the tokenizer.
* Every existing call site of `RevisionDiffer::diff()` is unchanged — the router is the
  seam, so a future swap stays contained (that was already the class's stated purpose).
* `RichText::toPlainText()` itself stays: the EPUB/export paths use it. Only the differ
  stops calling it.

## Consult

* `expanded/diffing.md` — *Two diff strategies, chosen by `FieldKind`*.
* `app/Services/RevisionDiffer.php`, `app/Support/RevisionDiffResult.php`,
  `resources/views/revisions/compare.blade.php` — what is being changed.

## Tests

`tests/Unit/RevisionDifferRoutingTest.php` (new):

* `FieldKind::Rich` output contains rendered block markup (the visual differ ran);
* `FieldKind::Markdown` and `Plain` output is the `jfcherng` side-by-side table
  (the source differ ran);
* a Rich pair differing only in formatting now returns a real diff — assert the old
  "formatting changed only" state is gone (a `RevisionDiffResult` with `html !== null`);
* `Scene.contents` never reaches `HtmlTokenizer` (spy/fake, or assert on output shape).

Plus: update any existing test asserting `formattingChangedOnly` (grep first — at least
`tests/Feature/RevisionHistoryTest.php` and the compare view's own coverage).
