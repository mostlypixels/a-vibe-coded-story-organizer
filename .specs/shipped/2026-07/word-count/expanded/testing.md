# Word Count — testing

## The PHP fixture table

**The rule for "what is a word" is this table**, asserted in
`tests/Unit/Support/WordCounterTest.php`. The server is authoritative; the JS counter is
indicative and is **not** held to this table (see *JS tests* below).

| Input | Kind | Expected | Why it is in the table |
|---|---|---|---|
| `""` | any | 0 | empty is 0, not 1 |
| `"   "` | any | 0 | whitespace only |
| `"one two   three"` | plain | 3 | runs of spaces collapse |
| `"one\n\ntwo"` | plain | 2 | newlines are separators |
| `"état d'âme"` | plain | 2 | `/u`; apostrophe does not split |
| `"jack-o'-lantern"` | plain | 1 | hyphenated stays one |
| `"one—two"` | plain | 1 | **Q1**: whitespace only, em-dash does not split |
| `"1,234"` / `"3.5"` | plain | 1 each | **Q2b**: has a digit, so it is a word |
| `"* * *"` | markdown | 0 | **Q2b**: scene divider, no letter or digit |
| `"—"` alone | plain | 0 | **Q2b**: non-word |
| `"\"  ...  |"` | plain | 0 | **Q2b**: punctuation only |
| `"**bold** text"` | markdown | 2 | markers are not words |
| `"# Heading"` | markdown | 1 | `#` is not a word |
| `"[link](http://x.com)"` | markdown | 1 | URL not counted, label is |
| `` "She typed `rm -rf` now" `` | markdown | 5 | **Q2**: inline code counts |
| ` "```\nfenced words here\n```" ` | markdown | 0 | **Q2**: fenced block stripped |
| ` "before\n```\ncode\n```\nafter" ` | markdown | 2 | only the fence is removed |
| `"<h1>Chapter One</h1><p>She waited.</p>"` | rich | 4 | **Q9**: heading must not glue |
| `"<ul><li>alpha</li><li>beta</li></ul>"` | rich | 2 | **Q9**: list items must not glue |
| `"<p>one</p><p>two</p>"` | rich | 2 | block boundary is a separator |

## Feature tests

`tests/Feature/WordCountTest.php` — plain PHPUnit, `RefreshDatabase`, factories,
`actingAs`, `route()` (the `ProjectTest` house style).

**The invariant, once per write path** — each asserts `scenes.word_count` matches the stored
`contents`:

1. Manual save via `SceneController::update()`.
2. **Autosave PATCH** via `FieldAutosaveController` — the path the writer actually uses.
3. **Revert a single field** via `revisions.revert` — the path a controller-level
   implementation would miss.
4. **Undo a whole save** via `revisions.saves.revert`.
5. Project import (`ProjectGraphImporter`).
6. Scene created by factory/seeder.

> [!IMPORTANT]
> Tests 3 and 4 are the ones that earn their place. They are the reason the count is a model
> hook: they pass trivially with the hook and fail with any controller-level implementation.
> If a future refactor moves the counting, these are the tests that will catch it.

**Totals:**

7. Chapter total = sum of its scenes; act total = sum across its chapters; project total =
   sum across its acts.
8. **Reparent**: move a scene to another chapter → both chapters' totals correct, with no
   fix-up code involved.
9. **Cascade delete** a chapter → its act's total drops by exactly that chapter's words.
10. **Move-on-delete**: delete a chapter moving its scenes elsewhere → the receiving
    chapter's total gains exactly those words, and nothing is lost.
11. A chapter with no scenes reports `0`, not `null`.

**Queries — the design's whole justification:**

12. `assertQueryCount` (or `DB::listen`) on the story overview: adding counts adds **zero**
    queries, because scenes are already eager-loaded.
13. The chapter index does **not** N+1: one grouped query regardless of chapter count.

**Rendering** — assert the number reaches the HTML, not just that the controller computed
it. (This feature's own version of the lesson from the revisions review: a green suite
asserted the app *decided* to flash a message and nothing asserted a page rendered one.)

14. Story overview response contains the chapter total.
15. Scene index response contains a scene's count.

## Migration test

`tests/Feature/AddWordCountToScenesMigrationTest.php`, following
`BackfillBaselineRevisionsMigrationTest`:

16. Scenes existing before the migration get correct counts backfilled.
17. **The backfill writes no revision rows** and does not bump `updated_at` — the trap in
    `data-model.md`.

## JS tests

`resources/js/word-count.test.js` (vitest). The counter is **indicative**, so these assert
it is sane, not that it equals the server:

18. Whitespace splitting: `"one two   three"` → 3; empty → 0; `"   "` → 0.
19. Debounces rather than recounting per keystroke.
20. An empty editor renders `0 words`, not a blank.
21. **Reconciliation**: given an autosave response carrying `word_count`, the displayed
    count becomes that number — including when it *disagrees* with what was shown while
    typing. This is the test that makes "indicative" safe; without it the two numbers just
    drift apart.

## Not covered by tests

Stated rather than left to look covered:

* **Live-vs-stored agreement is not asserted anywhere, by design.** They legitimately differ
  (fences, non-words). Only the *snap* on save is tested, in 21.
* **Real editor output** is never driven in a browser here. If TipTap's `getText()` shape
  changes, no unit test sees it. `/run-imagoldfish` on a scene with a heading, a list, a
  fenced block and an em-dash is worth more than another unit test — and is the only way to
  see the counter's placement against the autosave badge at mobile width (Q5).
