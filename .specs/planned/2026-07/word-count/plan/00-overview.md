# Word Count — plan overview

The manual. Never implemented, never moved to `implemented/`.

## Execution order

| # | Task | Purpose |
|---|---|---|
| 1 | `fix-plaintext-block-boundaries` | Fix `RichText::toPlainText()` gluing words across block boundaries — a live bug in search, and a prerequisite for counting anything |
| 2 | `word-counter-support-class` | `App\Support\WordCounter` — the one definition of "a word" |
| 3 | `scenes-word-count-migration` | `scenes.word_count` column, index, chunked backfill |
| 4 | `scene-saving-hook` | The invariant: the column always matches the stored `contents`, on every write path |
| 5 | `autosave-response-word-count` | The PATCH JSON returns the authoritative count |
| 6 | `word-count-blade-component` | `x-word-count` — one place formats a count |
| 7 | `live-counter-in-fields` | The indicative in-field counter, on all 14 autosaved fields |
| 8 | `story-overview-totals` | Totals on the story overview, at **zero** extra queries |
| 9 | `index-page-totals` | Totals on the scene/chapter/act indexes and the project header |
| 10 | `docs-and-changelog` | `documentation/`, `CHANGELOG.md` |

Dependency shape: 1 → 2 → 3 → 4, then 5, 6 and 8/9 fan out from 4 (6 before 8/9), 7 after 5,
10 last.

## Binding decisions — do not re-litigate

Settled in the spec or the `plan-tasks` grill (2026-07-27). Full reasoning in
`../expanded/open-questions.md`.

* **`word_count` on `scenes` only.** Chapter/act/project totals are a `SUM`. Benchmarked:
  the widest gap versus denormalising every level was 0.6 ms at 4,320 scenes / 6.3 M words,
  and the story overview already eager-loads scenes so its totals cost nothing. Do not add
  a column to `chapters`, `acts` or `projects`.
* **Only `scenes.contents` is counted or summed.** Never `description`, never `notes`.
* **A word is a whitespace-delimited token containing a letter or a digit.** `one—two` is
  one word. `* * *` is zero.
* **Fenced code blocks are stripped before counting; inline code is counted.**
* **The JS counter is indicative**, the server authoritative; they reconcile via
  `word_count` in the autosave response.
* **The live counter appears on all 14 `x-autosave-field` fields**, including `rights`.
* **No `wordCount()` accessor** on Chapter/Act — controllers `withSum`, views render.
* **The "Words" column is not sortable** in this feature.
* **Goals, targets and progress are out of scope** — the `word-count-goals` follow-up spec.

## Invariants every task must preserve

1. **`scenes.word_count` always equals `WordCounter::count($scene->contents,
   FieldKind::Markdown)` for the stored value** — after autosave, manual save, revert, undo,
   import and seeding. This is the feature's one invariant; everything else is derived.
2. **It is maintained by a model hook, never by a controller.** `RevisionReverter` writes
   through `$entity->save()` and never touches `FieldAutosaveController`; a controller-level
   implementation goes stale the moment someone uses Undo.
3. **No N+1.** Totals come from eager-loaded relations or `withSum` in the controller. A
   `->sum()` inside a Blade loop over unloaded scenes is the failure this design exists to
   prevent.
4. **Backfill and any bulk write use raw `DB::table()` updates**, never `$model->save()` —
   a model save fires `HasRevisions` and would invent revision rows nobody wrote.
5. **Authorization is unchanged.** No new endpoint; counts ride on already-authorized
   routes.
6. Existing invariants stand: `position` ordering, the un-deletable main plotline,
   authorization via `ProjectPolicy`.

## Verification

`composer test` and `composer lint -- --test` green after **every** task. `npm run test`
(vitest) for tasks 7 and any task touching `resources/js/`.
