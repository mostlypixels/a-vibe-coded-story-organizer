# 06 — The Narrow panel

## Scope

- An `x-collapsible-card` inside the existing GET form in `search/index.blade.php`, under
  the Match radios. `:open="$scope->isNarrowed()"`.
- Book `x-select`: `— Whole project —` plus each book by `Book::displayName()`. Rendered
  only when the project has more than one book; a single-book project sends the id as a
  hidden field instead.
- Chapter range: two `x-select`s, `From` and `To`, `— Start —` / `— End —` plus the book's
  chapters labelled `12. The Drowning`. Rendered only when a book is resolved; otherwise a
  short note that a book comes first.
- Domains: eight checkboxes, `name="domains[]"`, under the same Timeline / Story / Codex
  headings the results use, with a group-level "all" toggle.
- Not in scope: hiding result sections or the filter summary (task 07).

## Depends on

05.

## Key decisions

- **No JS submit, no second form.** The controls sit in the GET form that already exists,
  so they land in the URL for free.
- `x-collapsible-card` is already a `<details>`, so it nests in a form. Do not introduce a
  third disclosure pattern.
- Collapsed by default, open when the scope is narrowed — otherwise a bookmarked filtered
  search looks unfiltered.
- Chapter options come from the controller in story order. Never `orderBy('name')`.
- **None checked means all.** The default URL stays clean, and unchecking seven boxes is
  how "scenes only" is expressed. No separate control for it.
- The group toggle is presentation only. It must not add a ninth parameter.

## Consult

`expanded/ui.md` → *Narrow panel*.

## Tests

Extend `tests/Feature/SearchTest.php`.

- A single-book project renders no book select.
- A multi-book project with no book chosen renders the note, not the chapter selects.
- With a book chosen, the chapter selects list that book's chapters in story order.
- Submitted filters come back selected and the panel renders open.
- An unnamed book shows the project name, never `#<id>`.
