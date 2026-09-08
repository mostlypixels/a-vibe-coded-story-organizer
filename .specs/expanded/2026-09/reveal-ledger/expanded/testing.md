# Testing

## `ProjectStoryRank` — the foundation

Everything else is wrong if this is. Fixture: 3 books × 2 acts × 3 chapters × 2 scenes,
across one project.

- ranks run 1..N across books, continuing rather than restarting — the difference from
  `StoryNumbering`, which must keep restarting per book.
- `precedes()` is correct for scenes in different books, different acts, and the same
  chapter.
- `endOfBook()` returns the last scene of that book, and the first scene of the next book
  ranks exactly one higher.
- **`id` tie-breaks:** two books sharing a `position`, two acts sharing one, two chapters,
  two scenes — each ordered stably and identically on repeated builds. This is the failure
  that produces a ledger which changes answer between page loads.
- one query, not N+1. Assert the query count for a 3-book project.

## Reordering (criterion 5)

The feature's promise. Mark a reveal, then reorder, and assert the ledger's answer changed
with **no write to `reveals`**:

- move the reveal's chapter after a fact that was previously "already known" → the fact
  becomes hidden at that cutoff.
- move a whole book. Same assertion, coarser.
- assert `reveals.updated_at` is untouched throughout.

## Invariants

- a second reveal for the same fact is rejected (unique index and the friendly guard).
- `never_revealed = true` clears `scene_id`; setting a scene clears `never_revealed`.
- a scene from another project is rejected by the Form Request.
- `project_id` is taken from the fact, never from the request — post a forged `project_id`
  and assert the stored row ignores it.
- deleting the project deletes its reveals (the FK cascade, which is why the column exists).
- **deleting the reveal's scene leaves the orphan shape** the data model warns about, and
  every read path renders it without erroring. Whatever `open-questions.md` settles, this
  test pins the chosen behaviour.

## The three states

- unmarked, revealed and never-revealed render distinctly on the ledger and on the fact.
- an unmarked fact has no `reveals` row at all — absence is the state, not a row with two
  nulls.

## The leak warning

- a scene referencing an entry before its reveal scene is flagged.
- referencing *after* the reveal is not flagged.
- referencing in the same scene as the reveal is not flagged.
- attribute values and events are never flagged — the pivot does not cover them, and
  silently checking one type of three is the bug.
- the warning is derived, never stored: reorder so the reference falls after the reveal and
  assert the flag clears with no write.

## Ledger page

- "known by end of book N" counts every earlier book, not only book N.
- the three empty states each render.
- pagination behaves like every other list (reuse the `ListPaginationTest` shape).
- a non-owner gets 403 on the ledger page and on store/update/destroy.
