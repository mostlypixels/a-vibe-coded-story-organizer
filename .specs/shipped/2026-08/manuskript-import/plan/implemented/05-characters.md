# 05 — Characters

**Depends on:** 03 (independent of 04, but ordered after it).

## Scope

* `characters/*.txt` → `CodexEntry` of type `character`, in numeric filename order.
* The description builder: header fields → the HTML fragment described in
  `../expanded/architecture.md` → *Character description HTML*, amended by the grill as below.
* No aliases, no tags, no media, no `CodexAttribute`/`CodexAttributeValue` rows — character data is
  description text only, deliberately.

## Key decisions

* `Name` → `CodexEntry.name`. A file with no `Name` is skipped and counted.
* Skipped fields: `ID`, `Color`, `Importance`, `POV`, and any field whose trimmed value is `''` or
  `?`. Everything else imports in file order, including `Full Summary` and `Notes`.
* Per field: `<h3>Key</h3>` then one `<p>` per blank-line-separated block, single newlines inside a
  block as `<br>`. Values are `e()`-escaped before wrapping.
* A character whose fields are all skipped gets a null/empty description, not an empty `<h3>`.
* The result must survive `SanitizesRichHtml` unchanged — `h3`, `p`, `br` are all in
  `RichTextFields::ALLOWED_TAGS`.

## Tests

Extend `tests/Feature/ManuskriptImportCommandTest.php`: entry count and type; `name` from `Name`;
description contains a heading and value for a filled field; contains no heading for a `?` field nor
for `ID`/`Color`/`Importance`/`POV`; the multi-paragraph `Notes` becomes several `<p>` with `<br>`
inside a block; the all-`?` character imports with an empty description; the stored value read back
from the DB equals what was written (proving the sanitizer changed nothing); a name containing `&`
or `<` is escaped, not stripped.
