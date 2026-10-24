# Testing

## Unit: `SceneTextMatcher`

- Case: with match case on, `miss Lane` does not match `miss lane`. With it off, it matches.
- Whole word: `Lane` does not match `Laneway`, and it matches `Lane's` and `*Lane*`.
- Accents: `Helene` does not match `Hélène`. `é` at a word edge is a letter, so `Hélène` does not match inside `Hélènes`.
- Special characters in `find` (`.`, `*`, `(`) are literal.
- Multibyte offsets are byte offsets that `substr_replace` can use.
- Matches do not overlap: `aa` in `aaaa` gives 2.

## Feature: preview (`projects.replace.index`)

- The owner sees the matches grouped by scene in manuscript order, across books, acts and chapters with positions out of ID order.
- Scope: book, act and chapter each exclude scenes outside them.
- A blank `find` shows the form only and runs no query.
- Above the cap, the page shows the first 2,000 matches and a notice.
- A non-owner gets 403.
- Validation: a missing `find`, and a `find` that is too long.

## Feature: apply (`projects.replace.store`)

- Only the selected indexes change. Unselected matches in the same scene stay.
- One manual revision for each changed scene, with the label. No revision for a scene with nothing selected.
- `word_count` is updated, and codex references resync (a replace that adds a codex name adds the reference).
- Stale hash: an edit between preview and apply skips that scene, and other scenes still apply.
- A scene ID from another project is ignored.
- A non-owner gets 403. Bad `selection` JSON gives a validation error.
- An empty `replace` deletes the match.
- After apply, a per-scene revert (`revisions.saves.revert`) restores the old text.
