# Open questions

- **Paste into the editor is a third un-normalized route** (not in `spec.md`). Add a Tiptap
  `transformPastedText` hook calling the same convention? → **Recommend yes, same feature.**
  Import-only leaves the hole most writers actually hit. If it is deferred, say so in the
  documentation, not silently.
- **Fixture file location.** `resources/js/punctuation-fixtures.json`, or a neutral
  `tests/fixtures/punctuation.json`? → **Recommend `resources/js/`**: Vitest imports it with no
  path helper, PHP pays one `base_path()` call.
- **When the two disagree, which wins?** → **SmartPunct.** Teach the editor the digit-elision
  rule; it is one regex change to `openSingleQuote`, not a document-wide reimplementation.
- **`rock 'n' roll`.** → **Leave wrong, record it in the fixture file with a note.** Solving it
  needs a word list. One documented wrong answer in both places still meets the goal.
- **Backfill existing rows?** → **No.** Pre-V1, `migrate:fresh --seed`.
- **`StaticSiteExporter`.** Does it need the same treatment? It renders stored text, so
  canonical storage should make it moot — **verify, do not assume**, and add one assertion if
  it does not.
- **`ManuskriptImportCommand`** (branch `manuskript-import`, unmerged). Does it route through
  `readHtmlField`/`readMarkdownField`, or only `ContentSanitizer`? If the latter it inherits
  nothing. → **Check before merging that branch**; may need its own hook.
- **Should a writer keep a literal `--`?** → **No escape mechanism.** The editor has undo; the
  import path has none, and inventing one for a case nobody has asked for is scope.
- **Idempotence on re-import.** Export then re-import a normalized project: the second pass
  must change nothing. Confirm the transform is idempotent, especially quote direction.
