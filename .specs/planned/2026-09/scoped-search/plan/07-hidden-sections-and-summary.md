# 07 — Hidden sections and the filter summary

## Scope

- `search/index.blade.php`: a section whose domains are all `hiddenByBook()` does not
  render, and a muted line says why.
- A domain the reader simply unchecked renders nothing and says nothing.
- A filter summary line above the results, naming the active filters, with a *Clear* link
  back to the bare search.
- Not in scope: any service or controller change.

## Depends on

06.

## Key decisions

- **Hidden, not empty.** An empty codex column beside a filtered scene column reads as
  "she is not in this book", which is false.
- The explanatory line is the spec's wording: *Plotlines, events and the codex belong to
  the whole project. Clear the book filter to search them.*
- **Silence for an unchecked domain.** She turned it off; she does not need telling.
- The summary exists so a filtered empty result does not read as a broken search. It names
  the book, the range and the domain count in plain words.
- *Clear* points at the search route with `q` and `mode` only.

## Consult

`expanded/ui.md` → *What the filters hide*, *Filter summary*.

## Tests

Extend `tests/Feature/SearchTest.php`.

- A book filter renders no Timeline or Codex section, and shows the explanatory line.
- Unchecking Plotlines renders no Plotlines table and no explanatory line.
- The summary names an active book filter, and *Clear* drops every filter but keeps `q`.
- An unfiltered search renders no summary.
