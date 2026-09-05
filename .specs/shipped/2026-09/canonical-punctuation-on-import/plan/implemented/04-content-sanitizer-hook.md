# 04 — `ContentSanitizer` normalizes and returns

**Depends on:** 03.

## Scope

- Change `ContentSanitizer::assertHtmlAllowed()` and `assertMarkdownAllowed()` from `void` to
  returning the normalized string. Validate first — unchanged, still throws — then normalize
  and return.
- Update `ProjectGraphImporter::readHtmlField()` and `readMarkdownField()` to return the
  sanitizer's return value instead of the raw input.
- Extend the existing `ProjectGraphImporter` feature tests. See `expanded/testing.md` →
  *Import feature tests*.

**Not in scope:** the exporter (task 05), the editor (tasks 06–07), documentation (task 08).

## Key decisions

- The hook belongs here, **not** in `ProjectGraphImporter`. `ManuskriptImporter` (branch
  `manuskript-import`) calls the sanitizer directly at `ManuskriptImporter.php:234` and must
  inherit this for free.
- Keep the `assert*` names. They still assert; the return value is new, not a replacement.
  If a rename feels needed, raise it — do not do it silently.
- **The `ContentSanitizer` docblock must gain the exception.** It currently states that imports
  fail rather than change bulk content silently. Write the bound where the rule is: the
  allow-list still fails, punctuation is normalized only after it passes, and only punctuation.
- Titles and names come through `readJson()` and are not touched. Add a test proving it.

## Consult

`expanded/architecture.md` → *Where it hooks in*, *The `ContentSanitizer` principle*.
