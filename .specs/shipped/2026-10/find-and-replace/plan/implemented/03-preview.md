# 03 — Preview

## Scope

- Route `GET /projects/{project}/replace` → `FindReplaceController@index` (`projects.replace.index`).
- `FindReplacePreviewRequest`: `find` (it can be blank, for the landing state), `replace`, `match_case`, `whole_word`, and the scope fields as in `SearchRequest`. Reuse its rules; do not copy them.
- Value objects `FindReplaceQuery` and `FindReplacePreview` in `app/Support`.
- `SceneFindReplace::preview()`. Extract the scene query of `ProjectSearch::queryFor(Scenes)`, with its `LIKE` pre-filter, into a method both can call.
- The page `find-replace/index.blade.php` has the form, the scope panel and the scene groups:
  - The header with breadcrumb, a link to the scene and the match count.
  - The match rows with `<del>`/`<ins>` context.
  - A plain checkbox for each match, ticked, with no behaviour yet.
  - `<details>` for each scene, open when there are 20 matches or fewer in total.
- Empty state, and the notice when the 2,000 cap is reached.
- Links from the search page header (it carries `q` → `find`) and from the project navigation.
- `documentation/features/find-and-replace.md` (preview part), linked from `documentation/features/README.md`.
- Deferred to 04: the `store` route and apply. Deferred to 05: Alpine, select all/none, fold/unfold all, the counter and the sticky bar.

## Depends on

- 01, 02.

## Key decisions already made

- Authorize `update` on the project.
- A blank `find` runs no query.
- The context is about 60 characters each side, plain and escaped. Do not render Markdown.
- Each scene in the preview carries its `FieldHash::of($scene->contents)`. Each match carries its index in the scene.
- The cap counts matches, not scenes. Stop matching when the cap is reached.

## Docs

- `expanded/architecture.md` → Routes, Services, Limits.
- `expanded/ui.md` → Entry, Page, Preview list.

## Tests

- `tests/Feature/FindReplacePreviewTest.php`, see `expanded/testing.md` → Feature: preview.
