# Architecture

## Facts from the code

- Scene `contents` is Markdown, not HTML (`ProjectSearch::selectContentsThatMayMatch()`). Match against the raw stored string. Markup is plain characters, so a match can never cut an HTML tag.
- `ProjectSearch` is case- and accent-insensitive and gives one row per entity. It cannot be reused for matching. Reuse its scene query and its ordering only.
- `SearchScope` has a book and a chapter range, but no act. Act scope resolves to the act's chapter IDs (a range from the act's first chapter to its last).
- `Scene::booted()` recomputes `word_count` on `saving`. `SceneSaver::syncRelations()` resyncs the codex references through `SceneReferenceMatcher::syncScene()`.
- `RevisionRecorder` gives each entity its own save ID in a request. `revisions.saves.revert` reverts one entity, so per-scene undo works now with no change. Undo for the whole replace needs new work (see open questions).
- `FieldHash::of()` is the conflict hash that autosave and the reverter use.

## Routes

| Method | URI | Name | Action |
|---|---|---|---|
| GET | `/projects/{project}/replace` | `projects.replace.index` | Form, plus the preview when `find` is set |
| POST | `/projects/{project}/replace` | `projects.replace.store` | Apply |

- `FindReplaceController` with `index` and `store`, in the `SearchController` shape.
- Authorize `update` on `Project` for both actions: the preview is the first step of a write. Mirror this in each Form Request `authorize()`.
- Form Requests: `FindReplacePreviewRequest` and `FindReplaceApplyRequest`. `find` is required, non-empty after trim, with a max length. `replace` can be empty, so a replace can delete text. Booleans: `match_case`, `whole_word`. Scope fields as `SearchRequest` has them, plus `act`.

## Services

`app/Services/SceneTextMatcher.php`: pure, no database.

```php
/** @return list<array{offset: int, length: int}> byte offsets in $text */
public function matches(string $text, string $find, bool $matchCase, bool $wholeWord): array;
```

- Build the pattern with `preg_quote()` and the `u` flag. Add `i` when `matchCase` is off.
- Whole word: `(?<![\p{L}\p{N}_])` … `(?![\p{L}\p{N}_])`. Use these instead of `\b`, which fails on accented letters at the edges. An apostrophe is a boundary, so `Lane's` matches `Lane`. That is correct, and it is why the preview exists.
- Matches do not overlap, and they go from left to right.

`app/Services/SceneFindReplace.php` has two methods:

- `preview(Project, FindReplaceQuery, SearchScope): FindReplacePreview`
  - It uses the scene query of `ProjectSearch::queryFor(Scenes)`, in the same order. Extract it to a shared method; do not copy it. It also uses the `LIKE` pre-filter in both modes. The pattern ignores case and accents, so it returns more scenes than can match, never fewer. PHP decides.
  - For each scene it gives the contents hash (`FieldHash::of`), and for each match: the index, the offset and a context snippet.
- `apply(Project, FindReplaceQuery, array $selection, User): FindReplaceResult`
  - `$selection` maps scene ID to `{hash, indexes[]}`.
  - Each scene runs in its own transaction, through `RevisionRecorder::saveWithManualCheckpoint()` with `base_hashes = ['contents' => hash]`. A changed hash throws, so we skip that scene and record it as skipped.
  - Inside, it matches again on the locked contents. It replaces the selected indexes from the end to the start, so earlier offsets stay valid. Then it saves and calls `SceneReferenceMatcher::syncScene()`.
  - It loads the scenes through the project, so an ID from another project is ignored. Do not trust the submitted scene IDs.

Value objects in `app/Support`: `FindReplaceQuery` (find, replace, matchCase, wholeWord), `FindReplacePreview` (scene groups and totals), `FindReplaceResult` (counts and skipped scenes).

## Why match indexes, not offsets

The client sends the index of each match ("2nd match in scene 14") and the scene hash. If the hash matches, the server's match list is the same as the preview's. So indexes are enough, and the client can never send an offset that cuts a word.

## Limits

- Cap the preview at 2,000 matches. Above the cap, the page shows the first 2,000 and asks the author to narrow the scope. Apply refuses a submission with more than the cap.
- `max_input_vars` (PHP default 1000) would cut a large form. Send the selection as one JSON field (`selection`), not as one input for each match.

## Pitfalls

- A blank `find` must not run (the `SearchController` blank pattern).
- `replace` equal to `find` gives no change. Skip the save, so no empty revision is made.
- An empty `replace` with whole word on can leave a double space. It is the author's choice, and the preview shows it.
