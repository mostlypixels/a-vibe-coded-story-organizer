---
status: shipped
shipped: 2026-08-18
planned: 2026-08-16
expanded: 2026-08-16
---

# Multiple books

One major feature was missed during the development of the first version: the ability to have multiple books in a single project.

A project would start with a single book, but it would be possible to add more books to the project.

The codex is shared between all books, and a character could be referenced from any book.

## Book metadata

Moved from the project metadata to the book metadata.
The project keeps:

* Name
* Description
* Cover image
* Writing goals

## Navigation

### Dropdown menu

We would keep the dropdown menu, but with more levels.

* Project Name
  * Book 1
  * Book 2
* Other Project
  * Book 1

### Navigation bar

We work in the context of a book inside a project, so the "story" and "timeline" are bound to the book itself.

## Export

### Export epub

The books are exportable individually, we just change the project selector to a book selector.

### Export project

The entire project is exported but we add a new level of nesting to the "book" directory (so we have "books/booknumber/actnumber/...").
For the data, we also move "acts" one directory down inside a "books/id-book/acts/id-act/..."
