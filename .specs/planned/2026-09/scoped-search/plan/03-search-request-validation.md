# 03 — `SearchRequest` validation

## Scope

- Add to `SearchRequest::rules()`, all `nullable`: `book`, `from_chapter`, `to_chapter`,
  `domains` (array) and `domains.*`.
- Cross-request checks in `withValidator()`, because they depend on `book`, which arrives
  in the same request.
- Not in scope: using any of them (tasks 04+).

## Depends on

02.

## Key decisions

| Key | Rule |
|---|---|
| `book` | integer, exists in `books` for the route project |
| `from_chapter`, `to_chapter` | integer, exists in `chapters`, and the chapter belongs to the chosen book |
| `domains.*` | `Rule::enum(SearchDomain::class)` |

- **A cross-project id is a 422, never a silent ignore.** Route-model binding does not
  cover these — they arrive as query parameters.
- **`from_chapter` or `to_chapter` without `book` is a 422.** A range has no meaning
  without the book whose numbering it counts in. The single-book case still sends `book`,
  because the form renders it as a hidden field when there is only one.
- Messages name the field in plain words. The reader hand-edited a URL or followed a stale
  bookmark; "The selected book is invalid" is enough.

## Consult

`expanded/architecture.md` → *`App\Http\Requests\SearchRequest`*.

## Tests

Extend `tests/Feature/SearchTest.php`.

- Book from another project → 422.
- Chapter from another book in the same project → 422.
- `from_chapter` with no `book` → 422.
- An unknown domain value → 422.
- A bare search with none of these still renders.
