---
title: "Task 07 — Table of contents"
---

# Task 07 — Table of contents

## Scope

- `App\Support\NoteOutline`: from sanitized body HTML, return the heading list (level, text,
  anchor) and the body with `id`s added to `h1`–`h4`.
- **Contents** card on `notes/show` when there are 3 or more headings.

## Depends on

02.

## Key decisions already made

- Read page only.
- Anchors are added at render time. Stored HTML and the sanitizer allow-list do not change.
- Repeated heading text gets unique anchors (`-2`, `-3`).

## Docs

`expanded/architecture.md` → Where logic lives; `expanded/ui.md` → Note read view.

## Tests

- Unit `NoteOutlineTest`: levels, unique anchors, no headings, unicode text.
- Feature: card shows at 3 headings, hidden at 2; stored body unchanged after a page view.
