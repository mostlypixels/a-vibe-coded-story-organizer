---
title: "Task 05 — Notes card on linkable pages"
---

# Task 05 — Notes card on linkable pages

## Scope

- Component `x-notes-card :notes :linkable`: title, excerpt, "New note", "Link a note", unlink.
- Place it on the read and edit pages of book, act, chapter, scene, event, plotline, codex entry.
- Eager-load `notes` in those controller actions.

Defers: replacing the scene Notes field with this card (09). Until then the scene edit page has
both.

## Depends on

04.

## Key decisions already made

- Read and edit pages both.
- Unlink has no confirm dialog.
- "Link a note" uses `linkPicker.js` against `notes.candidates`, posting to `notes.links.store`.

## Docs

`expanded/ui.md` → Notes card on linkable pages.

## Tests

- For each of the seven types: the read and edit pages list a linked note and do not list an
  unlinked one.
- No N+1: one test with several notes asserts a fixed query count on one page.
