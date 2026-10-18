---
title: "Task 01 — Codex alias search"
---

# Task 01 — Codex alias search

## Scope

- `LinkTargetType::candidates()` for `Codex`: match the entry name **or** any `codex_aliases` row of the entry (`CodexEntry::aliases()`), with `LikeSearch::whereContains`.
- One row per entry. Label stays the entry name. Order by name.
- Both callers gain it: `projects.link-targets` and the note-link candidates.

Does **not** touch the editor. Task 08 uses the endpoint.

## Depends on

None.

## Key decisions already made

- Use `whereHas('aliases', …)` in an `orWhere` group, so the project scope stays outside the `or`.
- Other types do not change.

Detail: `expanded/architecture.md` → *Reused services*.

## Tests

In `tests/Feature/LinkTargetControllerTest.php`:

- An alias match returns the entry once, with the entry name as label.
- An entry with two matching aliases appears once.
- An alias of another project's entry does not match.
- Name match still works; the limit still applies.
