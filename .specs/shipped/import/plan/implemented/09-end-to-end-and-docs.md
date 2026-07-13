# Task 09 — End-to-end round-trip and documentation

## Scope

Close the loop with a full export→import round-trip feature test exercising the
whole stack (no mocking of any layer built in tasks 01–08), then update
documentation. This is the acceptance-criteria pass from `overview.md`.

* `tests/Feature/ImportRoundTripTest.php`: seed a project with a non-trivial tree
  (multiple acts/chapters/scenes with deliberately non-sequential authoring order,
  a renamed main plotline, a non-fixed event, a codex entry with aliases, tags,
  attribute values anchored to different events, and cover + reference media),
  export it via `StaticSiteExporter`, import the resulting zip via the full HTTP
  route, and assert the new project matches the source on every axis
  `testing.md` → *Round-trip* describes. Repeat once with `include_images = false`.
* `documentation/architecture.md`: add a "Static site import" section parallel to
  the existing "Static file export" section, cross-referencing
  `documentation/export-format.md` and noting the checkpoint/resume design (link to
  `.specs/expanded/import/expanded/data-model.md` conceptually, but write the
  section to stand on its own — don't require a reader to open the spec folder).
* `documentation/export-format.md`: remove/update the "future import" framing now
  that import exists — it currently says things like "a future import remaps
  these ids"; update those sentences to reflect that the importer described here is
  now built (e.g. "an import remaps...", no longer "a future import").
* `CHANGELOG.md`: one `## [Unreleased]` → `Added` entry for the import feature.

## Depends on

Every prior task (01–08) — this is the integration/acceptance task.

## Key decisions already made

* Nothing new — this task verifies the accumulated design, it doesn't introduce any.

## Docs to consult

`overview.md` → *Acceptance criteria* (this task's test should cover every bullet);
`testing.md` → *Round-trip* section.

## Tests

* The full round-trip test described above, both with and without media bytes.
* Re-run the exact same zip through import a second time in the same test and
  assert two distinct, correctly-disambiguated projects exist.
* Run `composer test` for the whole suite (not just the new tests) to confirm
  nothing in tasks 01–08 regressed anything else — this is the last checkpoint
  before the feature is considered shippable.
