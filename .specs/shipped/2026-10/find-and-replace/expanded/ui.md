# UI

## Entry

- A "Find and replace" link on the search page header, next to the search box. It carries `q` over as `find`.
- A link in the project navigation, near Search.

## Page `find-replace/index.blade.php`

- The form has the fields find, replace, "Match case" (ticked), "Whole words" (ticked) and the scope. Submit is GET "Preview".
- Scope: reuse `x-search.narrow-panel` for book and chapter range, and add an act select. If the panel cannot take an act without forking, put the act select beside it.
- With no preview, show only the form (the search landing state).
- With zero matches, show an empty state with the scope summary (`SearchScopeSummary`).

## Preview list

- A sticky bar has the counter, "Select all", "Select none" and the "Replace N" primary button. The button is disabled at 0.
- For each scene:
  - A header with book / act / chapter / scene name, a link that opens the scene in a new tab, the match count, a tri-state checkbox (all, some, none) and "Replace in this scene".
  - It folds with `<details>`. All scenes are open when there are 20 matches or fewer, and folded above that.
- For each match row: a checkbox, and the context of about 60 characters each side. The old text is in `<del>` and the new text in `<ins>`. Use theme tokens, no new colours.
- Show the context as plain text, escaped. Do not render the Markdown, so the author sees the raw `*` and other markup.

## Alpine

- One component holds `selection`, as `{sceneId: Set(indexes)}`, filled from data in the page.
- The page state drives the checkboxes. "Select all" and "Select none" change the state, not the DOM, so folded scenes are included.
- On submit, write `selection` and the hashes as JSON into a hidden `selection` input.
- "Replace in this scene" submits the same form with only that scene in `selection`.
- Before a submit of more than 50 matches, show a summary line ("Replace 214 matches in 61 scenes?") in an inline panel, not `confirm()`.

## After apply

- Redirect to the preview with the same query, which now shows the matches that are left. Show a status message with the counts and a list of skipped scenes with links.
- Each changed scene's revision list shows the replace label.
