# 04 — Apply

## Scope

- Route `POST /projects/{project}/replace` → `FindReplaceController@store` (`projects.replace.store`).
- `FindReplaceApplyRequest`: `find` (required), `replace`, `match_case`, `whole_word`, and `selection` (JSON: `{sceneId: {hash, indexes[]}}`). Validate the shape, integer indexes, and a total of 2,000 or fewer.
- `SceneFindReplace::apply()` and the `FindReplaceResult` value object.
- Redirect to `projects.replace.index` with the same query, a status message with the counts, and the skipped scenes.
- A plain submit button on the preview page that posts every match as selected, so the flow works before task 05.
- Add the apply part to `documentation/features/find-and-replace.md`, and add the label to `documentation/features/revisions.md`.
- Deferred to 05: the selection UI and "Replace in this scene".

## Depends on

- 03.

## Key decisions already made

- Each scene runs in its own transaction, through `RevisionRecorder::saveWithManualCheckpoint()` with `base_hashes = ['contents' => hash]`. A conflict skips that scene. The other scenes go on.
- Match again on the locked contents. Replace the selected indexes from the end to the start.
- Resync `SceneReferenceMatcher::syncScene()` after the save. Extract a `SceneSaver` method for a contents-only update if that is cleaner.
- The label is `Replace "<find>" with "<replace>"`, a translatable string.
- No save when nothing changes (no indexes, or `replace === find`).
- Load the scenes through `$project->sceneQuery()`. Ignore unknown IDs.

## Docs

- `expanded/architecture.md` → Services, Why match indexes, Pitfalls.
- `expanded/ui.md` → After apply.

## Tests

- `tests/Feature/FindReplaceApplyTest.php`, see `expanded/testing.md` → Feature: apply.
