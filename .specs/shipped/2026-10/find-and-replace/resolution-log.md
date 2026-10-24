# Find And Replace — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

An exception log: a task that went to plan gets no entry, because the diff and the task
file already record what was built. Bullets under the headings below, root cause first.

## Feedback & decisions

- Undo is one scene at a time. Undo for the whole replace needs a batch ID and a revert across scenes. That is too much for v1, and the preview with checkboxes already stops most mistakes.
- The page is separate from search, because search lists entities and only reads.
- Find matches raw Markdown. Skipping markup would make the place of `*` in the replacement unclear.
- The open-editor case uses the existing autosave conflict, because the app does not track open editors.
- The cap is 2,000 matches. Scenes fold above 20 matches, with "Unfold all" and "Fold all" buttons (the author asked for the buttons).
- Find always respects accents, because an accent-insensitive replace would overwrite accents.
- Both actions authorize `update`. `view` and `update` are the same today, but the preview is the first step of a write.
- Act scope goes in `SearchScope`, so the search page gets it too. An act and a chapter range together give an error, not an overlap.

## Deviations from the spec/plan

- The source spec's leaning was undo for the whole replace. The grill chose undo for each scene.
- Task 01 said `ProjectSearch` needs no change. It does: `scopeBookAndRange()` and `scopedActQuery()` also filter on `acts.id`. An act with no chapters resolves to an empty `chapterIds`, which reads as "the whole book".
- Nav link: the Tools menu, the Tools page card and a link on the search page. The spec said "near Search"; the top bar is full, and this is a tool. `toolsActive` includes `projects.replace.*`, and `Breadcrumbs::toolsTrail()` has a branch for it.
- `find` is not trimmed and may be whitespace-only. The spec said "non-empty after trim", but a replace of double spaces is a real job. `bootstrap/app.php` exempts `find` and `replace` from `TrimStrings`. Blank means an empty string.
- `FindReplacePreviewRequest` extends `SearchRequest` and drops the `q`, `mode` and `domains` rules. This reuses the scope rules and the cross-checks with no copy, and `SearchScopeFactory` accepts it.
- `x-search.narrow-panel` got a `domains` prop (default true). The replace page passes false. A new `ScopePicker` builds the book, act and chapter lists for both controllers.
- Apply counts a match only when its text changes. With match case off, `lane` over `Lane` is a change; `Lane` over `Lane` is not. A scene with no change gets no save and no revision. The task said "no save when `replace === find`"; this covers that and the case-only edit.
- Apply sends the result to the page as `replace_result` in the session (`replaced`, `scenes`, `skipped`), not as a `status` string, because the page needs counts and links.
- "Replace in this scene" posts that scene's selected matches, not all of its matches, and is disabled when none are selected. The ticks stay the single source of what is replaced.
- The JS-only controls use the `hidden` attribute with `:hidden="false"`, not `x-cloak`: the app has no `[x-cloak]` CSS rule, and Tailwind v4 makes `[hidden]` `display: none !important`.

## Issues → resolutions

- `mb_strcut($text, $start, $length)` moves $start back to a character edge, and then ends before the match. The context before a match lost its last bytes (a missing space) when the cut fell inside a multibyte character. Fix: move $start forward to the edge, then `substr()`. The suite did not catch it until the test text was long enough to hit the cut.
- `scripts/assets-state.sh` fails in the Docker setup: `public/hot` belongs to the Docker Vite server, and the native dev database is not the Docker one. Neither blocks a Docker check.
- `FindReplaceApplyRequest` could not use the `required` rule for `find`. `required` rejects a string of spaces, which the log above allows. An `after` hook checks for an empty string instead.
- `NoBrowserPopupsTest` matches `confirm(` anywhere in `resources/js` and `resources/views`. The Alpine method for the inline confirm panel was first named `confirm()` and failed it. Renamed to `accept()`. Only the full suite caught it, not the filtered run.
