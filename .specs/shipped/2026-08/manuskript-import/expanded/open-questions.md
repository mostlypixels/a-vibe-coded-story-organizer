# Open questions

* **Act naming.** Recommend `"Act 1"` with a `--act=` override — matches the app's default naming
  and stays neutral. Alternative: name it after the project.
* **Scene status.** Recommend leaving every scene at the column default; Manuskript's `status.txt`
  vocabulary is user-defined (French here) and does not map onto `SceneStatus`. Confirm the default
  is the wanted one (`draft`) rather than "to edit" for imported prose.
* **Character `ID`/`Color`/`Importance`/`POV` fields.** Recommend importing them as ordinary
  headings — no special case. They are noise in the description; the alternative is a small skip
  list.
* **Markdown gate strictness.** Recommend failing the whole import on disallowed rendered HTML
  (reusing `ContentSanitizer`). Alternative: skip the scene and count it, so one stray line cannot
  block a migration.
* **Multi-line values → `<br>` vs `<p>`.** Recommend `<br>` inside the single `div`, matching the
  spec's "content goes to a div" literally. `<p>` per paragraph would read better for long `Notes:`.
* **Do we ever need the character `Notes:` body split out?** Recommend no — one description field,
  as specified.
* **Where the fixture lives.** `tests/Fixtures/` does not exist yet; recommend creating it rather
  than generating the tree in the test's `setUp()`.
