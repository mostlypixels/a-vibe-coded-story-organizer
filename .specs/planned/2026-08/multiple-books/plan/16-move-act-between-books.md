# 16 — Move an act to another book

**Depends on:** 07.

## Scope

- A destination-book control on the act edit page, and a `PATCH /acts/{act}/move-to-book` route
  handled by `ActController`.
- Reuse `ReparentsChildren`'s rule for the act itself: append it after the destination book's
  current maximum `position`, so no two siblings collide.
- Authorize through `$act->book->project`, and prove the **destination** book belongs to the same
  project — a foreign destination is a 403, never a silent cross-project move.

**Not in scope:** moving a book between projects. That stays out of the feature.

## Key decisions

- **`position` is not reassigned on a plain parent change** — the `creating` hook only fires on
  insert. The move must set `position` explicitly, the same trap `ReparentsChildren` documents.
- **The FK is not mass-assignable.** `book_id` stays out of `$fillable`, so the move goes through
  `->book()->associate()`, never `update(['book_id' => …])`, which is silently dropped.
- The act keeps its chapters and scenes; only its parent changes. Numbering in **both** books
  shifts as a consequence — that is correct, and task 04's derivation handles it with no stored
  value to fix up.
- The control is hidden when the project has one book.

## Consult

`expanded/ui.md` → *Story pages*; `app/Http/Controllers/Concerns/ReparentsChildren.php` for the
two pitfalls it already documents.

## Tests

- `ActTest`: the act lands in the destination book, appended last, with its chapters and scenes
  intact.
- Numbering in both books is recomputed correctly after the move.
- A destination book in another project is a 403; a non-owner is a 403.
