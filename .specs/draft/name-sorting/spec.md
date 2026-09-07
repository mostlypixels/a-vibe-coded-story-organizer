---
status: draft
---

# Name sorting

> [!NOTE]
> **Low priority.** There is not enough real data to test this properly. The demo
> projects have ten to fifty entries, all seeded with tidy capitalised names, so
> the defect is invisible and any fix would be checked against cases chosen to
> prove it works. Revisit when a project with a real cast — mixed case, accents,
> articles, numbered chapters — exists to measure against.

Every list sorts by name through SQL, and SQLite compares text byte by byte. That is
not reading order:

- `Oliver` sorts before `Ollie`, because uppercase `i` precedes lowercase `l`.
- Anything starting lowercase falls after `Z`, at the bottom of the list.
- Accented letters sort after every unaccented one, so `Mélusine` is nowhere near `M`.

At twelve rows nobody notices. Pagination made it matter: across sixteen pages a name
can sit pages away from where a reader would look for it, and there is no longer a
browser-find to rescue them.

## Goals

- Sorting by name puts names where a reader expects them.
- One rule, every list — codex entries, tags, scenes, chapters, books.
- The rule survives the database, rather than depending on one engine's default
  collation. See `multiple-database-engines`.

## Non-goals

- No natural or numeric sort ("Chapter 10" after "Chapter 9"). Story order already
  exists for anything with a position, and `list-jump-to-position` is where reaching
  a chapter belongs.
- No per-user or per-language collation choice.
- No change to sorting by anything other than a name.
- No search relevance ranking.

## Approach

- Case-insensitive comparison is the whole of the visible defect and the cheapest fix.
  Accents are the harder half and may not be worth the same solution.
- Options, in rising cost: a collation on the column, a collation on the query, or a
  stored sort key written beside each name. A sort key is the only one that behaves
  the same on every engine, and it is also the only one that can go stale.
- Whatever is chosen must apply where the sort happens, which today is a bare
  `orderBy('name')` in several controllers plus `ResolvesIndexSorting`.

## Open ends

- Whether accents and articles ("The Fountain" under T or F) are in scope at all, or
  whether case is the only real complaint.
- Whether SQLite's `NOCASE` is enough, given it is ASCII-only and this app already
  ships French and Italian demo data.
- What a proper test looks like without representative data. A fixture of real names
  may need to come first, and may be the actual first task.
