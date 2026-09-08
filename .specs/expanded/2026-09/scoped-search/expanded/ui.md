# UI

All controls live inside the existing GET form in `resources/views/search/index.blade.php`,
so they land in the URL for free. No JS submit, no second form.

## Narrow panel

A `<fieldset>` under the Match radios, in the same card. Collapsed by default, expanded
when `$scope->isNarrowed()` — otherwise a bookmarked filtered search looks unfiltered.

Use `x-collapsible-card`'s pattern if it fits inside a form; otherwise a plain
`<details>`. Do not introduce a third disclosure pattern.

### Book

`x-select`, `— Whole project —` plus each book by `Book::displayName()`. Rendered only
when `$project->books()->count() > 1`.

An unnamed book prints the project name. Never `#<id>`.

### Chapter range

Two `x-select`s, `From` and `To`, each `— Start —` / `— End —` plus the book's chapters in
story order, labelled with their `StoryNumbering` number and name (`12. The Drowning`).

Rendered only when a book is resolved: the chosen one, or the only one. Otherwise a note
saying a book must be chosen first.

Options come from the controller, ordered by `(acts.position, chapters.position,
chapters.id)` — **not** `orderBy('name')`. `SceneController::chaptersFor()` orders by name
today; `list-jump-to-position` is the spec that fixes that. Do not reuse it until it does.

### Domains

Eight checkboxes, `name="domains[]"`, grouped under the same three headings the results
use — Timeline, Story, Codex — with a group-level "all" toggle. None checked means all,
so the default URL stays clean.

This absorbs the "scenes only" case: uncheck the other seven. A separate control for it
would be a second way to say the same thing.

## What the filters hide

With a book chosen, the Timeline and Codex sections do not render at all. A muted line
says why:

> Plotlines, events and the codex belong to the whole project. Clear the book filter to
> search them.

Empty columns would read as "no matches", which is a lie.

## Filter summary

Above the results, one line naming the active filters with a *Clear* link back to the bare
search. Without it a filtered empty result looks like a broken search.

## Links that must carry the scope

- `See all :count results` — `components/search/result-table.blade.php`
- *Back to search* and the paginator — `search/domain.blade.php`

Both through `SearchScope::toQuery()`. Never hand-built.
