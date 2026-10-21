---
status: draft
---

# No Curly Quotes

## Problem

The app always changes straight quotes (`"`, `'`) to curly quotes. The import does it
(`CanonicalPunctuation`), and the editor does it when you type and paste (`wysiwyg.js`,
`punctuation.js`). The rule looks only at the character before the quote, so some quotes
curl in the wrong direction. One example is `*"Hi"*` on import. A writer who wants
straight quotes cannot stop the change.

## Goals

- A setting that turns the quote change on or off.
- Import obeys the setting.
- The editor obeys the setting when you type and when you paste.
- The default stays "curly quotes", so nothing changes for a writer who does not use the setting.

## Non-goals

- Fix the direction rule. That is a separate fix.
- Make dashes or ellipses optional. They keep their current behavior.
- Convert text that is already stored. Changing the setting affects new input only.
- Change exports. They show the stored characters.

## Rough approach

- Store the flag next to the existing settings. Follow the `ImportSetting` / `RevisionSetting` pattern.
- When the flag is off, skip only the quote step in the three implementations. The fixture
  `tests/Fixtures/punctuation.json` must still cover both modes.
- Give the editor the flag in the same way that it gets its other configuration.

## Open questions

- Scope: one global setting, a setting for each project, or a setting for each user?
- One flag for import and writing, or two flags?
- Single quotes too, or only double quotes? Apostrophes (`don't`) use the same character.
- Does the Backspace undo after a quote change stay as it is?
