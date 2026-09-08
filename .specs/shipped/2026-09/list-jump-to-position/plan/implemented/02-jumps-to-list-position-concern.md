# 02 — `JumpsToListPosition` concern

## Scope

**In:** `app/Http/Controllers/Concerns/JumpsToListPosition.php` — the trait that turns a
Go-to request into a redirect.

**Out:** using it. Neither controller calls it yet (tasks 03 and 04), no Blade changes, no
button. This task adds a trait nothing yet uses.

## Depends on

01.

## Contract

```php
protected function jumpRedirect(
    Request $request,
    string $route,                // 'books.scenes.index' | 'books.chapters.index'
    Book $book,
    Builder $rows,
    string $column,
    EloquentCollection $groups,   // story-ordered, from chaptersFor()/actsFor()
    int $perPage,
    string $fragmentPrefix,       // 'chapter' | 'act'
): ?RedirectResponse
```

1. `null` unless `jump` is filled — this is the "not a Go-to request" exit, and it must be
   the first thing checked.
2. Read the destination from the select's own parameter: `chapter` on the scene list, `act`
   on the chapter list. Derive it from `$fragmentPrefix` rather than adding a ninth
   argument.
3. **Reject an id absent from `$groups`.** Same allow-list discipline `ResolvesIndexSorting`
   applies to `sort`. `$groups` is already book-scoped, so this is the parameter's
   authorization boundary. A rejected id redirects to the bare index — never returns `null`,
   or `jump` would survive into a rendered page.
4. Build the URL with **only** `page`, `highlight`, `sort=position`, `direction=asc`. Search,
   filter and `jump` are dropped by construction, not by subtraction — do not start from
   `$request->query()` and unset keys.
5. Append `#<fragmentPrefix>-<id>`.

## Key decisions

- Follows `ResolvesIndexSorting`'s shape: the trait resolves, the caller returns. Read that
  file first — its docblock explains why the allow-list living in one place is the point.
- **Go to always clears search and filter and always forces story order.** Binding; see
  `00-overview.md`. There is no branch on whether the target has matches.
- The redirect is issued from `index()` *before* any query building, so a jump does no
  filtering, no aggregate and no pagination work.

## Tests

The trait has no route of its own, so test it through a controller — which means these
tests land with task 03. **What this task must leave behind** is the trait plus whatever
minimal harness proves it in isolation; if that is awkward, say so in
`resolution-log.md` and let 03 own the assertions rather than inventing a fake route.

At minimum, verifiable here:

- an id outside `$groups` produces a redirect to the bare index, not `null`.
- a request with no `jump` produces `null` without touching the database.

## Consult

`../expanded/architecture.md` → *`JumpsToListPosition` concern* and *The two parameters*.
`app/Http/Controllers/Concerns/ResolvesIndexSorting.php` for the house shape.
