# Word Count — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

Settled in the `plan-tasks` grill, 2026-07-27. Reasoning in `expanded/open-questions.md`.

* **Totals count `scenes.contents` only**; the live counter appears on all 14
  `x-autosave-field` fields. Confirmed explicitly — a counter on `notes` or `rights` feeds
  no total and is a local convenience.
* **A word is a whitespace-delimited token containing a letter or a digit.** `one—two` is
  one word (matches Word/Scrivener, which is what a writer compares against); `* * *` is
  zero.
* **Fenced code blocks are excluded from the stored count; inline code is counted.** A fence
  is marked as not-prose; inline code sits inside a sentence.
* **The JS counter is indicative, the server authoritative**, reconciled by `word_count` in
  the autosave response. This changed the response key from a nice-to-have into a
  requirement, and released the JS from matching the PHP fixture table.
* **`word_count` on scenes only, ancestors summed.** Benchmarked before deciding: 150 / 960
  / 4,320 scenes, widest gap 0.6 ms at 6.3 M words, and the story overview already
  eager-loads scenes. Denormalising every level would have bought an imperceptible read and
  paid four write paths that must each fix up two ancestors.
* **Goals/targets/progress deferred** to a `word-count-goals` follow-up: counting needs one
  integer per scene, progress needs a per-day time series.
* **Task 5's "non-scene field" means "not `scene.contents`", not "not on the `Scene`
  model".** Only `scenes.word_count` is a stored column (binding decision, task 3), and only
  `contents` keeps it current (task 4's hook checks `isDirty('contents')`). So `Scene`'s own
  `description`/`notes` autosaves — same model, different field — read `$model->word_count`
  too if taken literally, which would report the *contents* count while editing notes.
  `FieldAutosaveController::update()` therefore special-cases `$model instanceof Scene &&
  $field === 'contents'` specifically, and computes every other field (`Scene.description`,
  `Scene.notes` included) on the fly with `WordCounter`.

* **The app has no `lang/`/`resources/lang` files at all** — confirmed by searching, not
  assumed. Every `trans_choice` call in the codebase (`ProjectController`,
  `delete-with-move-dialog.blade.php`, `projects/show.blade.php`,
  `revisions/compare.blade.php`) uses the inline pluralisation string directly as the
  translation key (`'{0} :count words|{1} :count word|[2,*] :count words'`), never a lang
  file lookup. `x-word-count` follows that exact convention rather than introducing a lang
  file — no French/Italian string was added, matching that no comparable existing string has
  one either.

## Deviations from the spec/plan

* **Task 1's block-tag set is the allow-list ∪ what `Str::markdown()` emits, not the task's
  literal list.** Added `h5`/`h6` (absent from `RichTextFields::ALLOWED_TAGS`, which stops at
  `h4`, but commonmark emits all six and `Scene.contents` renders through it) and `td`/`th`
  (the task named only `tr`, yet `<td>a</td><td>b</td>` is exactly the gluing bug). Dropped
  `figcaption`: neither the sanitizer nor commonmark can produce it.
* **Tests went into the existing `tests/Unit/RichTextTest.php`**, not the
  `tests/Unit/Support/` path the task named — the class already existed one directory up, and
  a second `RichTextTest` would have been a duplicate rather than a move.

* **Task 6's claim that `x-word-count` "should be picked up by `BladeComponentCompilationTest`
  automatically" does not hold yet — confirmed, not assumed.** That test only walks a fixed
  list of routes (`BladeComponentCompilationTest::pages()`); no page renders
  `<x-word-count>` until tasks 7–9 wire it into the story overview and the index pages, so it
  currently passes on this feature's changes vacuously, not because it exercises the new
  component. `WordCountComponentTest` (this task) is what actually pins the component's
  markup down in the meantime. Whoever implements task 8 or 9 should expect
  `BladeComponentCompilationTest` to start covering `x-word-count` only once a listed page
  actually contains it — no action needed here, just don't mistake today's green run for
  that coverage already existing.

## Issues → resolutions

* **`RichText::toPlainText()` glued words across block boundaries** (found while grilling
  the design, before any code). It converted only `</p>` and `<br>` to newlines, so
  `<h1>Chapter One</h1><p>She waited.</p>` became `Chapter OneShe waited.` — 3 words instead
  of 4, and lists collapsed entirely. Scene contents render through `Str::markdown()`, so
  any chapter with a heading or a list would have undercounted.

  It is also a **live bug in shipped search**: `ProjectSearch` uses the same helper, so
  snippets render the glued text and `SearchMode::ExactPhrase` cannot match across a
  boundary. Matching itself is unaffected (`str_contains` is substring). Resolved by fixing
  the shared helper in task 1, ahead of anything that counts, rather than giving
  `WordCounter` a private extractor — two helpers disagreeing about the text of a document
  is worse than one change with a blast radius.

  **`SearchMode::ExactPhrase` still cannot span a block boundary, and that is intended.**
  The separator is `"\n"` and `AccentFolder::fold()` normalises case/accents only, never
  whitespace — so "One She" does not match `<h2>…One</h2><p>She…`. A phrase the writer never
  wrote on one line is not a phrase match. Asserted both ways in
  `ProjectSearchTest::test_an_exact_phrase_does_not_match_across_a_block_boundary` so the
  next change to the separator has to face the decision.

  No existing assertion pinned the glued output, so the task's expected "update the tests
  that encode the bug" turned out to be nothing to update — the whole suite (1133) was green
  on the fix alone.

* **`DatabaseSeeder` uses `WithoutModelEvents`**, which wraps the whole `db:seed` run — every
  seeder it `$this->call()`s, including MelusineSeeder{En,Fr,It} — in `Model::withoutEvents()`.
  Task 4's assumption ("the seeders write through the model, so the hook fills word_count
  with no seeder change") was half right: they do write through `$chapter->scenes()->create()`,
  but the hook itself never fires there, so every seeded scene landed at `word_count = 0`
  until confirmed by the seeded-scene assertion `data-model.md` asked for. `MelusineSeederEn`
  already carried a visible scar from the same cause — its main-plotline creation has a
  `firstWhere('is_main', true) ?? Plotline::create([...])` fallback because `Project::booted()`'s
  auto-create-on-`created` hook is equally suppressed there.

  Fixed with `Database\Seeders\Concerns\BackfillsSceneWordCounts` (one `backfillSceneWordCounts()`
  call per project, added to all three Melusine seeders right after their story tree is built),
  mirroring the `scenes.word_count` migration's own raw `DB::table()` backfill rather than
  re-enabling model events for the whole seeded run — removing `WithoutModelEvents` would also
  turn on `HasRevisions`'s baseline-seeding for every autosavable field on every seeded row, a
  much larger and unrelated behavior change.

* **`WordCounter::count()` threw on malformed UTF-8** (`Str::markdown()` requires valid
  UTF-8/ASCII and throws `League\CommonMark\Exception\UnexpectedEncodingException` otherwise).
  Latent since task 2 — nothing called `WordCounter` at write time yet — task 4's
  `Scene::booted()` hook made it reachable on every save, and `Scene.contents` has no
  sanitizing mutator (unlike the rich fields), so a writer's paste or an old import can leave
  bad bytes in the column. Surfaced by `SceneReferenceMatcherTest`'s own malformed-UTF-8
  fixture scene, which stopped being creatable at all. Fixed by guarding `WordCounter::count()`
  with `mb_check_encoding()`, returning 0 for invalid input — the same treatment
  `SceneReferenceMatcher` already gives this exact failure mode, so the two agree rather than
  one crashing where the other degrades.

* **`AddWordCountToScenesMigrationTest` (task 3) broke** once the hook existed: it simulated
  "a scene from before this migration" by calling the migration's `down()` (dropping the
  column) and then `Scene::factory()->create()` — which now tries to write `word_count` into a
  column that test had just removed. Rewrote it to insert those fixture rows with a raw
  `DB::table('scenes')->insertGetId()` instead, which is actually the more honest simulation:
  a real pre-migration row never went through this hook (or any Scene model code) either.

* **Confirmed, not assumed:** `ProjectGraphImporter::importScene()` creates through
  `$chapter->scenes()->create()` — no bulk `insert()`, hook fires, no importer change needed.

* **Task 4's test 7 did not test what it claimed.** The plan called it "proves the `isDirty`
  guard", and it was written as: rename a scene, assert `word_count` is unchanged. That passes
  whether or not the guard exists — recounting unchanged contents returns the same number, so
  there is nothing for the assertion to catch. Verified by deleting the guard and watching it
  still pass. Rewritten to write a deliberately wrong count (`999`) straight to the column
  first, so only a guarded hook leaves it standing; that version fails with the guard removed.

  Worth generalising: the plan's *Tests* sections say what each test is **for**, and a test
  that names an invariant is not the same as one that would notice the invariant breaking.
  The task file already demanded this proof for tests 3 and 4 (patch the hook out, watch them
  fail) — the same treatment was owed to test 7 and was not applied. Re-verified tests 3 and 4
  independently at review time: with the hook removed all 8 fail, so those two are honest.

* **Task 7's bottom-left badge / bottom-right counter row used `justify-content: space-between`,
  which broke at the common case (idle, no autosave in flight).** The badge span is
  `style="display: none;"` at idle, and a `display:none` flex child is removed from layout
  entirely, not just hidden — with one visible child left, `justify-between` has nothing to
  distribute the space against and collapses to flex-start, so the counter rendered bottom-left
  instead of bottom-right. No test caught this (jsdom/Blade::render() never lays anything out);
  found only by driving `scenes/edit` in a real browser per the task's own instruction. Fixed by
  dropping `justify-between` and giving the counter `ml-auto` instead, so its position no longer
  depends on the badge's visibility.

* **Task 7 shipped the same could-not-fail test shape as task 4's test 7, one task later.**
  `AutosaveFieldComponentTest::test_the_live_word_count_for_scene_contents_reuses_the_stored_column`
  asserted the rendered count against a *correct* `scenes.word_count`, which is the number
  `WordCounter::count()` returns for the same contents anyway — so it passed with the
  `$model instanceof Scene` branch deleted (verified by deleting it). Rewritten to plant
  `word_count = 999` straight into the column first, the same fix task 4's test 7 got; it now
  fails without the branch and passes with it. The lesson generalises: whenever a test asserts a
  number two code paths both produce, the number has to be one only the intended path can yield.

## Deviations from the spec/plan (task 7)

* **Extracted `App\Support\WordCountFormat`, and edited task 6's `word-count.blade.php` to use
  it**, though task 7 named only `word-count.js` and `autosave-field.blade.php`. The live counter
  needs the same "N words" pluralisation as `x-word-count` but as an unfilled template (a literal
  `%d`, not a number baked in) to fill in client-side — duplicating the translation key inline in
  both files would leave two copies to update in step. `WordCountFormat::text()` is what
  `word-count.blade.php` now calls (byte-for-byte the same output as before); `jsTemplates()` is
  the new method `autosave-field.blade.php` uses to hand the three branches to
  `resources/js/word-count.js`.

* **Mechanism for reading editor text**: `resources/js/wysiwyg.js`'s `onUpdate` now also dispatches
  a bubbling `wysiwyg:text-changed` CustomEvent carrying `editor.getText()`, mirroring the
  `autosave:explicit-leave` arm's-length pattern already used between `navigation-guard.js` and
  `field.js`. The counter's Alpine component wraps the field's editor/textarea (not just its own
  `<span>`) specifically so this event, and a plain textarea's native `input` event, both bubble
  up to something it's listening on.

* **Mechanism for reconciliation**: `resources/js/autosave/field.js`'s `save()` looks up this same
  wrapping element via `this.$root.querySelector('[data-word-count]')` (the same DOM-querying
  pattern `fieldValue()` already uses to reach the textarea across the `x-wysiwyg` nested-`x-data`
  boundary) and dispatches `word-count:reconcile` on it with the response's `word_count`,
  unconditionally on every successful save.

* **Task 8's totals test used round numbers, and `assertSee` is a substring match.** Its
  fixture read 1,075 / 1,050 / 1,000 / 50 / 25 / 20 / 5, so the chapter asserting `50 words`
  was satisfied by its own act's `1,050 words`, and `5 words` by `25 words` — both assertions
  passed with those chapter totals never rendered. The docblock claimed the opposite, and the
  break-it probe missed it because breaking the view failed the *other* assertions first.
  Refixtured to values where no total is a tail of another (1,343 / 1,062 / 1,015 / 47 / 281 /
  213 / 68). **The general rule, now paid for three times on this feature:** an assertion has
  to name a value only the intended element can produce — distinct is not enough when the
  matcher is `str_contains`.

* **Task 9's acts-index test gave each act a single chapter**, so it could not distinguish
  "sums every chapter through `Act::scenes()`" from "sums the first one". Act A now spans two
  chapters (821 + 179 + 500 = 1,500), which is what actually exercises the `HasManyThrough`.

## Task 9 — index page totals

* **The plan's `$acts = $project->acts()->withSum('chapters.scenes as word_count', 'word_count')`
  does not work — confirmed, not assumed.** `withSum`/`withCount` resolve their argument as a
  literal relation *method name* on the model, not a dot-nested path; calling it throws
  `BadMethodCallException: Call to undefined method App\Models\Act::chapters.scenes()`
  (verified via a tinker scratch script before writing any controller code). The fix needed no
  new relation: `Act::scenes(): HasManyThrough` already exists (added for the edit page's
  cascade-count dialog), so `ActController::index()` sums through that directly —
  `->withSum('scenes as word_count', 'word_count')` — one query, confirmed via query log.

* **`Project` has no equivalent one-hop relation to sum through** — project → act → chapter →
  scene is two levels of indirection, and Eloquent's `hasManyThrough` only bridges one
  intermediate table, so neither a dot-nested path nor a plain `hasManyThrough` covers it.
  `ProjectController::show()` also only ever renders one project, not a list, so `withSum`
  (built for eager-loading an aggregate onto a *collection*) is the wrong shape regardless.
  Used the project's own `sceneQuery()` Builder instead (already walks chapter → act → project
  for the scene index and search): `$project->sceneQuery()->sum('word_count') ?? 0` — one query,
  confirmed via query log.

* **`Builder::sum()` already returns `0`, not `null`, for zero matching rows** (confirmed via
  tinker on an empty project) — unlike `withSum`, which leaves the raw SQL `NULL` on the
  model attribute when a chapter/act has no scenes (also confirmed). The `?? 0` on the
  `sceneQuery()->sum(...)` line in `ProjectController::show()` is therefore redundant in
  practice; kept anyway so the line reads the same "never blank" invariant as the two `withSum`
  coalesce loops, rather than depending on a reader knowing `sum()`'s own default.

## Final pass — browser verification (tasks 1, 8, 9)

* **The act/chapter totals were inside the headings.** Task 8 rendered `x-word-count` as a
  child of the `<h2>`/`<h3>`, so each heading's accessible name became "Act 1 — Melusine's
  Youth 490 words" — screen-reader heading navigation read the count as part of the title, and
  the act was silently renamed every time the writer added a sentence. Deferred at the time
  because nobody had looked at the page; confirmed in a browser (`h2.innerText`) and fixed by
  making the coloured bar a wrapper `<div>` and the count a sibling of the heading. Visually
  identical; `id` and `scroll-mt-16` stay on the heading, so the table-of-contents anchors
  still land on a heading element (the page nav is `position: static`, so the 8 px of bar
  padding above it changes nothing).

  `StoryTest::test_totals_render_beside_the_act_and_chapter_headings_not_inside_them` guards it
  by asserting against each heading's **own inner HTML** — on the page as a whole "613 words"
  is present either way, which is exactly the shape of assertion that would pass with the count
  moved back inside. Verified by moving it back in and watching the test fail.

* **Task 1's search snippets were checked in a browser at last.** The scene snippets prove
  nothing about it — `ProjectSearch::plainFieldValues()` only routes **rich** fields through
  `RichText::toPlainText()`, and `Scene.contents` is Markdown, used raw. No seeded searchable
  rich field spans more than one block (only the project's own description does, and the
  project is not itself searched), so a two-paragraph description was written to a codex entry
  in the dev DB: the snippet renders "…the tower Quillon guards…", separated, with both terms
  highlighted independently. The dev DB was reseeded afterwards.

* **The seeder backfill was confirmed end-to-end** by `migrate:fresh --seed` followed by a
  browser load: the story overview still totals 2,946 words, so neither the migration's
  backfill nor `BackfillsSceneWordCounts` is quietly a no-op.
