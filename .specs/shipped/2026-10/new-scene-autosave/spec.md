---
status: shipped
shipped: 2026-10-19
planned: 2026-10-19
expanded: 2026-10-19
---

# New Scene Autosave

## Problem

The New Scene page can lose words. The scene does not exist yet, so the page has no autosave.
The unsaved-changes guard reads only the autosave store, so a nav click, a reload or a tab
close drops the text with no warning. Edit pages are safe: autosave saves within seconds.

## Goals

- Words typed for a new scene reach the database as soon as possible.
- The writer keeps typing without a break when the scene gets created.
- From that point on, the edit page's autosave, History and leave guard apply.

## Non-goals

- No `localStorage` draft. It was removed on purpose: `.specs/shipped/2026-08/no-localstorage-with-autosave/`.
- Other create forms (chapters, codex, notes). One page first; reuse the result later if it works.
- Crash recovery for text typed before the scene exists.

## Rough approach

- Create the scene as soon as it has a chapter and a title. Then continue on the edit page,
  which already has autosave.
- Follow the existing autosave and edit-page patterns. Do not add a second save path.

## Open questions

- When exactly is it created: on title blur, on a debounce, or on the first keystroke in the body?
- Can the writer type the body before giving a title? If yes, what holds that text until creation?
- Hand-over: a redirect to the edit page (focus and cursor must survive), or turn the same page into the edit form in place?
- A scene the writer abandons right after creation: keep it, or remove it when it is still empty?
- Does the leave guard need to cover the time before the scene exists?
