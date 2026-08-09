# Word count

One stored number — `scenes.word_count` — behind every count the writer sees: the live
counter in each prose field, the per-scene figure on the story overview and index pages, and
the chapter / act / project totals summed from it.

## The counting rule

`App\Support\WordCounter::count($value, FieldKind)` is the **only** definition of "a word".
Four steps, always in this order:

1. Strip fenced code blocks — Markdown input only, on the source text, before rendering.
2. Render to plain text by kind (`Str::markdown()` for Markdown, `RichText::toPlainText()`
   for rich HTML, as-is for plain).
3. Split on whitespace only.
4. Drop any token containing neither a letter nor a digit.

What that buys, and what it costs:

| Input | Counts as | Why |
| --- | --- | --- |
| `one—two`, `jack-o'-lantern` | 1 | Whitespace split — matches Word and Scrivener |
| `* * *` | 0 | Step 4 drops punctuation-only tokens |
| `1,234`, `3.5` | 1 | A digit is enough to keep a token |
| `**bold**` | 1 | Rendered before splitting, so markup never counts |
| A fenced code block | 0 | Not prose |
| `` `inline code` `` | counted | It sits inside a sentence |
| Four-space indented code | counted | CommonMark stops seeing a fence at four spaces |

> [!NOTE]
> Malformed UTF-8 returns **0, not an error**. `Str::markdown()` throws on invalid bytes, and
> the saving hook below makes that reachable on every save — a bad paste would 500. This
> matches how `SceneReferenceMatcher` already treats the same failure on the same column.

Fences are stripped from the **source** rather than found as `<pre>` in the rendered HTML:
what the parser already knew is cheaper and less fragile to use than to re-derive. Closed
fences are removed first, so whatever still matches an *opening* fence is genuinely unclosed
(CommonMark treats the rest of the document as code, and so does this).

## Only scenes, only `contents`

- **Only `scenes.contents` is ever counted or summed** — never `description`, never `notes`.
- **Only `scenes` gets a column.** Chapter, act and project totals are a `SUM`. Benchmarked
  at 150 / 960 / 4,320 scenes: the widest gap versus denormalising every level was **0.6 ms
  at 6.3 M words**, and the story overview already eager-loads its scenes, so its totals cost
  nothing at all. Do not add a `word_count` to `chapters`, `acts` or `projects`.
- The column is `unsignedInteger` with `default(0)`, never nullable — 0 is a real answer
  ("no words yet"), so no caller has to handle "unknown".
- A composite index on `(chapter_id, word_count)` makes the per-chapter `SUM` covering: the
  aggregate reads the index and never touches the `contents` blob.

## The invariant

**`scenes.word_count` always equals `WordCounter::count($scene->contents,
FieldKind::Markdown)` for the stored value** — after autosave, manual save, revert, undo,
import and seeding. Everything else is derived from it.

It is held by a `saving()` hook in `Scene::booted()`, guarded by `isDirty('contents')`:

- **A hook, not a controller.** `RevisionReverter` writes through `$entity->save()` and never
  touches `FieldAutosaveController` — a controller-level count goes stale the moment someone
  uses Undo.
- **`saving`, not `saved`.** It sets an attribute on the row already about to be written: no
  second `UPDATE`, no half-applied state.

> [!WARNING]
> **Bulk writes use `DB::table()`, never `$model->save()`.** A model save fires `HasRevisions`
> and would write a revision row per scene — a migration or seeder inventing thousands of
> "edits" nobody made — and would bump `updated_at`. Both existing bulk paths follow this: the
> `scenes.word_count` migration's chunked backfill, and `BackfillsSceneWordCounts`.

> [!NOTE]
> **Seeding does not fire the hook.** `DatabaseSeeder` uses `WithoutModelEvents`, which wraps
> every seeder it calls, so scenes created by a seeder would all land at 0. Each Melusine
> seeder therefore uses the `BackfillsSceneWordCounts` trait once its story tree exists.
> Removing `WithoutModelEvents` would be the wrong fix — it would switch on `HasRevisions`
> baseline-seeding for every seeded row.

## Totals without an N+1

Ancestor totals are aggregated **in the controller**, never in the view:

| Page | How |
| --- | --- |
| Act index | `->withSum('scenes as word_count', 'word_count')` |
| Chapter index | `->withSum('scenes as word_count', 'word_count')` |
| `projects/show` header | `$project->sceneQuery()->sum('word_count')` |
| Story overview | `->sum()` over the **already eager-loaded** act → chapter → scene tree — no query fires |

> [!WARNING]
> `withSum` leaves the attribute **`NULL`** for a row with no scenes — SQL `SUM` has no rows
> to sum. Both index controllers normalise with `??= 0` so an empty act or chapter renders
> "0 words" rather than blank.

Two traps worth knowing before you copy the pattern:

- An act sums through its **own `scenes()` `HasManyThrough`**. A dot-nested path like
  `withSum('chapters.scenes', …)` is not a relation name and throws `BadMethodCallException`.
- **There is deliberately no `wordCount()` accessor** on `Chapter` or `Act`. An accessor is an
  invitation to call it inside a Blade loop, which is exactly the N+1 this design exists to
  prevent. Controllers aggregate; views render.

## Rendering a count

`x-word-count` is the one place a count is formatted, so a table cell and a header can never
disagree about `1,234` vs `1234`, or about singular versus plural:

- `count` — a plain **int**, never a model. A scene's own column and a computed `SUM` both go
  through it, which is why it takes an int rather than an entity.
- `variant` — `muted` (default: small, grey — headers and asides) or `inline` (inherits the
  surrounding size and colour — table cells).
- **Zero renders "0 words"** — never blank, never a dash. Zero is a real answer; blank reads
  as "unknown".

`App\Support\WordCountFormat` holds the single `trans_choice` key behind it. `text()` returns
the finished string for Blade; `jsTemplates()` returns the same three pluralised branches with
a literal `%d` still in place of the number, so the browser can fill in what it just counted
without shipping its own copy of the wording or of English pluralisation.

## The live counter is approximate — by design

This is an **accepted cost**, not a defect. `resources/js/word-count.js`'s `countWords()`
splits on whitespace and does nothing else: no fence stripping, no punctuation filter. So
while you type inside a fenced code block the counter climbs, and the moment the field saves
it settles back down.

The alternative was shipping a second Markdown parser to the browser to produce a number that
is about to be thrown away — because every autosave `PATCH` returns the server's
authoritative `word_count`, and the counter reconciles to it. The server is the only authority;
the browser only fills the gap between saves.

The two halves stay at arm's length through DOM events rather than reading each other's Alpine
state, the same pattern `navigation-guard.js` uses:

- A real `<textarea>`'s native `input` event bubbles to the wrapping `[data-word-count]`.
- TipTap has no native input event to bubble (its `syncTextarea()` assigns `.value` directly,
  which fires nothing), so `wysiwyg.js` dispatches a bubbling `wysiwyg:text-changed` carrying
  `editor.getText()` — already rendered text, even in Markdown mode.
- `autosave/field.js` finds the same element after a save and hands it the server's count.

Counting is debounced ~150 ms — short, because nothing goes over the network; it exists only
to stop recounting on every keystroke.

## History and goals

`scenes.word_count` is also the only input to a project's writing history:
`Scene`'s `saved`/`deleted` hooks call `App\Services\WordCountSnapshotRecorder`,
which records the project's `SUM` onto a `word_count_snapshots` row for the
writer's local day. That history, plus two open-ended goals
(`daily_word_goal`, `total_word_goal`), power the Tools ▸ Progress chart and
the dashboard card. Full reference: [`word-count-goals.md`](word-count-goals.md).

## Where things live

| Concern | Location |
| --- | --- |
| The definition of "a word" | `app/Support/WordCounter.php` |
| Formatting + the one translation key | `app/Support/WordCountFormat.php` |
| The invariant (the `saving` hook) | `app/Models/Scene.php` (`booted()`) |
| Column, index, one-time backfill | `database/migrations/…_add_word_count_to_scenes_table.php` |
| Seeder backfill (events are off) | `database/seeders/Concerns/BackfillsSceneWordCounts.php` |
| Authoritative count in the autosave response | `app/Http/Controllers/FieldAutosaveController.php` |
| Rendering | `resources/views/components/word-count.blade.php` |
| Live counter | `resources/js/word-count.js` (mounted by `components/autosave-field.blade.php`) |
| Plain text from rich HTML | `app/Support/RichText.php` — see [`rich-text.md`](rich-text.md) |
