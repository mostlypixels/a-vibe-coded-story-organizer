# Find and replace

[Documentation](../README.md) › [Features](README.md) › Find and replace

Find and replace changes text across the scenes of one project. The author sees every match first and chooses which ones to change.

## Pages

| Route | Name | Does |
| --- | --- | --- |
| `GET /projects/{project}/replace` | `projects.replace.index` | The form, and the preview when `find` is set |
| `POST /projects/{project}/replace` | `projects.replace.store` | Replaces the selected matches, then redirects to the preview |

- Entry points: the Tools menu, the Tools page, and a link on the search page that carries `q` over as `find`.
- Both actions need `update` on the project. The preview is the first step of a write.
- The page is not part of the search page: search lists entities and only reads.

## Matching

- `App\Services\SceneTextMatcher` finds the matches. It is pure and has no database access.
- The find text is literal. `.`, `*` and `(` are plain characters.
- Match case and whole words are on by default. The form sends a hidden `0` before each check box, so a missing field means a first visit and an unticked box means off.
- Find is always accent-sensitive. An accent-blind replace would overwrite accents.
- Find matches the raw Markdown. `miss Lane` does not match `miss *Lane*`.
- Whole word uses letter and number lookarounds, not `\b`. An apostrophe is a boundary, so `Lane's` matches `Lane`.
- `find` and `replace` skip the `TrimStrings` middleware (`bootstrap/app.php`). A single space is a valid find text.

## Preview

- `SceneFindReplace::preview()` builds a `FindReplacePreview`: scene groups in manuscript order, then matches.
- The scenes come from `ProjectSearch::sceneQuery()`, the same query the search page uses. The `LIKE` pre-filter only skips scenes that cannot match. PHP decides every match.
- A blank `find` runs no query.
- Each scene group carries `FieldHash::of($scene->contents)`. Each match carries its index in the scene and about 60 characters of plain, escaped context on each side. The page does not render the Markdown.
- The preview stops at `SceneFindReplace::MAX_MATCHES` (2,000) matches. The page then asks the author to narrow the scope.
- Scene groups are open with 20 matches or fewer in total, and folded above that.
- The scope is `SearchScope`: book, act or chapter range. An act together with a chapter range is a validation error. Domains do not apply.

> [!WARNING]
> Match indexes, not byte offsets, identify a match. The apply step may trust an index only while the scene hash still equals the hash from the preview.

## Apply

- `FindReplaceApplyRequest` extends the preview request. `find` must not be empty, but it may be spaces. The `required` rule would reject spaces, so an `after` hook checks it.
- `selection` is one JSON field: `{"<sceneId>": {"hash": "…", "indexes": [0, 2]}}`. One input for each match would pass `max_input_vars`. The request rejects bad JSON, a non-integer or negative index, and more than `SceneFindReplace::MAX_MATCHES` indexes in total.
- `SceneFindReplace::apply()` loads the scenes with `$project->sceneQuery()`. A scene ID from another project is ignored.
- Each scene runs in its own transaction, through `RevisionRecorder::saveWithManualCheckpoint()` with `base_hashes = ['contents' => hash]`.
  - The recorder locks the row and compares the hash. A changed hash throws, and the scene goes in the skipped list. The other scenes still apply.
  - On the locked contents, the matcher runs again. The selected indexes are replaced from the end to the start, so earlier offsets stay valid.
  - `Scene::booted()` updates `word_count`. `SceneReferenceMatcher::syncScene()` resyncs the codex references.
- A match whose text already equals the replacement is not counted. When no match changes, the scene gets no save and no revision.
- Each changed scene gets one manual revision labelled `Replace "<find>" with "<replace>"`. Each text is cut at 60 characters, because the label column holds 255. Undo is per scene, with `revisions.saves.revert`. There is no batch ID.
- The controller redirects to the preview with the same query. The session carries `replace_result` (`replaced`, `scenes`, `skipped`). The page shows the counts and links to the skipped scenes.

## Selection

- The Alpine component `findReplace` (`resources/js/find-replace.js`) holds the selection as `{sceneId: Set(indexes)}`. The page fills it from `FindReplacePreview::selectAll()`, so every match starts selected.
- The state drives the check boxes. "Select all" and "Select none" change the state, so they also reach folded scenes.
- A sticky bar holds the counter, "Select all", "Select none", "Unfold all", "Fold all" and "Replace N". "Replace N" is off at 0.
- "Unfold all" and "Fold all" set `open` on every `<details>`. They do not change the selection.
- Each scene header has a tri-state check box (`data-state`: `all`, `some`, `none`) and "Replace in this scene". That button posts the same form with only that scene in `selection`.
- A submit writes the JSON into the hidden `selection` input. Above 50 matches (`CONFIRM_ABOVE`), an inline panel asks "Replace N matches in K scenes?" first. The page does not use `confirm()`.
- The JS text comes from `ScriptTranslations::findReplace()`.
- Without JavaScript, the selection controls stay hidden (`hidden` until Alpine binds it off), and the button posts every listed match.

