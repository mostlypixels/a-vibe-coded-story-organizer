# New Scene Autosave — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

An exception log: a task that went to plan gets no entry, because the diff and the task
file already record what was built. Bullets under the headings below, root cause first.

## Feedback & decisions

- **Create form drops prose, not "create in the background".** A redirect to the Write page gets
  the same result with no new JS or second save path.
- **Write page, not edit page, after create.** It is the daily writing loop.
- **Description out of the create form too.** It is prose and can be lost the same way.
- **No leave warning on the create form.** Only a title and selects remain.
- **Write page dialog is in scope** (Cindy: "now"). Chapter + title only, left panel, end of chapter.
- **Empty scenes stay.** No auto-delete.

## Deviations from the spec/plan

- **The dialog asks the leave guard through an event, not by opening its modal.** `navigationGuard.confirmLeave()` reads its own `pendingHref`, so a bare `open-modal` would leave to null. `navigation-guard.js` now listens for `navigation-guard:ask` (`{ href }`), sets `pendingHref`, then opens `unsaved-changes-guard`.

## Issues → resolutions

- **`WysiwygFormTest` and four store tests assumed the old create page and redirect.** The suite asserted `books.scenes.create` holds `contents` and `description` editors, and that `scenes.store` redirects to the index. Fix: dropped the create URL from the editor lists and pointed the redirects at `scenes.write`. The plan named only the `SceneTest` create test.
- **`assets-state.sh` fails while the Docker stack runs.** It checks the native path: `public/hot` is the Docker Vite server, and the native SQLite is behind migrations. The Docker DB had no pending migrations; the browser check ran against Docker.
