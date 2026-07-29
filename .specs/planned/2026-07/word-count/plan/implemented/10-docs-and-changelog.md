# Task 10 — Documentation and changelog

## Scope

**`documentation/architecture.md`** — a compact section, linking out rather than explaining
in place (the *entry point short, deep dive linked* rule):

* `scenes.word_count` is the only stored count; ancestors are a `SUM`, with the one-line
  reason (benchmarked, and the story overview already eager-loads scenes).
* The count is maintained by a `Scene` `saving()` hook, so it survives revert and import —
  and **must not** be moved into a controller.
* Add the page to the index table if a `documentation/word-count.md` is created.

**`documentation/word-count.md`** — only if the architecture section starts outgrowing a
short block. Contents if written: the counting rule (whitespace split, letter-or-digit
tokens, fences out / inline code in), why JS is indicative and how it reconciles, and the
N+1 pitfall.

**`documentation/rich-text.md`** — note that `RichText::toPlainText()` separates *all* block
boundaries (task 1), since search snippets and any future consumer depend on it.

**`CHANGELOG.md`** — one dated section, no PR number (`pr-land.sh` stamps it):

```markdown
## YYYY-MM-DD — See how long it is

### Added
- A live word count in every prose field, and stored counts per scene…
```

Write it for a **writer**, not a developer: what they can now see and where. No class names.

## Depends on

Tasks 1–9.

## Key decisions already made

* Documentation explains **why**, not what — the invariant, the pitfall, the rejected
  alternative (denormalising every level, with the measured numbers).
* Record the accepted cost: **the live counter is approximate** between saves, by design.
  That is a consequence of a decision, not a defect, and belongs where the next reader will
  find it.

## Consult

All of `../expanded/`, and `../resolution-log.md` for anything that deviated during
implementation.

## Tests

* `tests/Unit/DocumentationLinksTest` must stay green — every relative link resolves and any
  new page is listed in `architecture.md`'s index.
* No feature tests; this task ships prose.
