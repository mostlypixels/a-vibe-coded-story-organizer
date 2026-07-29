# Task 8 — Totals on the story overview, at zero extra queries

## Scope

`resources/views/story/index.blade.php` shows a word count per chapter, per act, and for the
project.

`StoryController` already eager-loads `chapters.scenes.event`, so **every scene is already in
memory**. Sum in PHP:

```php
$chapter->scenes->sum('word_count')
$act->chapters->sum(fn ($chapter) => $chapter->scenes->sum('word_count'))
```

Compute in the controller and pass down, or compute in the view from loaded relations —
either is fine here **because the relations are loaded**. What must not happen is a query.

Render through `x-word-count`.

## Depends on

Tasks 4 and 6.

## Key decisions already made

* **No new query.** This page is the design's showcase: the scenes-only column plus existing
  eager loading means totals are free. If this task adds a query, something is wrong with
  the approach, not with the budget.
* **No `Chapter::wordCount()` accessor** — it would hide whether a query fires. Sum the
  loaded collection explicitly.
* A chapter with no scenes shows `0 words`.

## Consult

`../expanded/ui.md` (placement), `../expanded/architecture.md` (reading totals).

## Tests

Extend `tests/Feature/StoryTest.php` or add to `WordCountTest`:

* Chapter total = sum of its scenes; act total = sum across its chapters; project total =
  sum across its acts.
* **Query count is unchanged** by adding counts — capture with `DB::listen` around the
  request before and after, or assert an explicit count. This is the assertion that proves
  the whole caching decision.
* An act with no chapters, and a chapter with no scenes, both render `0 words`.
* The number **reaches the HTML** — `assertSee` the rendered total, not just that the
  controller computed it.

> [!IMPORTANT]
> That last one is this feature's version of a lesson already paid for: the revisions review
> found a green suite asserting the app *decided* to flash a message while nothing asserted a
> page rendered one. Assert the number is on the page.
