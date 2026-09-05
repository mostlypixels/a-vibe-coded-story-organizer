# 08 — Documentation

**Depends on:** 07.

## Scope

Rewrite every place that describes the invariant this feature deletes:

- `documentation/features/rich-text.md` lines 90, 127, 136. Line 136 states the old rule
  outright ("which is why `EpubExporter` keeps its own SmartPunct pass").
- `documentation/export-import/epub.md:27`.
- `resources/js/wysiwyg.js` comments at lines 41 and 72 — they justify the editor's rules by
  agreement with the exporter's pass. Point them at `tests/Fixtures/punctuation.json` instead.
- `resources/js/wysiwyg.test.js` comments at 618 and 655, same reason.
- One dated `CHANGELOG.md` section.

Add a short section naming `tests/Fixtures/punctuation.json` as the definition of canonical
punctuation, and the three implementations that assert against it.

## Key decisions

- ASD-STE100, per `CLAUDE.md`.
- Document the `ManuskriptImporter` inheritance: it gets this through `ContentSanitizer`, with
  no code of its own.

- `app/Services/EpubExporter.php:171` — the converter property docblock still says "The private
  SmartPunct converter for this export." Task 05 missed it. The extension is gone; rename what
  the converter is for.
