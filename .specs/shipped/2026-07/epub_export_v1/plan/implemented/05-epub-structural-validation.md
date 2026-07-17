# 05 — Epub structural validation & empty-project handling

## Scope

- Vendor EPUB 3 OPF/nav schema files (XSD and/or RelaxNG) sourced from the `epubcheck`
  project's own repository (confirmed permissively licensed during planning) into the repo,
  e.g. under `resources/epub-schemas/` — record the exact source URL/commit in a comment or
  short README in that folder so future updates know where they came from.
- Add two validation steps inside `EpubExporter`'s pipeline, run before the final `.epub` path
  is returned:
  1. **Well-formedness**: every generated XHTML content document (from task 03) parsed with
     `DOMDocument::loadXML()`, libxml internal errors captured (not suppressed). Any error is
     a server-side bug — throw loudly (do not silently degrade or drop the offending page).
  2. **Schema validation**: the generated OPF and nav documents (from task 04) validated
     against the vendored schemas via `DOMDocument::schemaValidate()`. Same failure handling.
- New `app/Exceptions/EpubExportException.php` (or `app/Services/Exceptions/...` — match
  wherever this project keeps custom exceptions, check for a precedent first), thrown by
  `EpubExporter::export()` when the filtered tree (task 03's filtering) ends up completely
  empty — this is a **user input problem** (nothing to export), distinct from the
  well-formedness/schema failures above (which are **bugs**, not user errors, and should not
  reuse this exception class).

## Explicitly not in scope

- Catching `EpubExportException` and turning it into an HTTP response — that's task 06.
- Running the real `epubcheck` Java tool anywhere in this pipeline (explicitly out of scope
  for the whole feature — see `overview.md` non-goals).

## Depends on

03 (content documents to well-formedness-check) and 04 (OPF/nav documents to schema-validate).

## Key decisions already made

- No Java/JVM dependency anywhere — this is PHP-native `DOMDocument` validation only.
- A well-formedness or schema failure is a **bug in the generator**, not a validation error to
  show the user — let it throw/500/log, don't catch-and-degrade.
- An empty filtered tree is the **one** condition that should look like a normal validation
  failure to the end user (task 06 turns it into a redirect-back-with-error).

## Docs to consult

- `expanded/architecture.md` — the Validation section's exact rationale for the bug-vs-user-error
  distinction.
- `expanded/open-questions.md` (question 6, now resolved during the second grill) — the
  `epubcheck` repo is the schema source.

## Tests

- Well-formedness: feed `EpubExporter` a project whose scene content would (hypothetically)
  produce malformed XHTML if the pipeline had a bug — or, more practically, add a direct unit
  test on the validation method itself with a deliberately malformed XHTML fixture string,
  asserting it throws/logs rather than silently passing.
- Schema validation: a direct unit test on the validation method with a deliberately
  non-conformant OPF fixture (e.g. missing a required element), asserting it throws.
- Happy path: a normally-generated `.epub` from a well-formed project passes both checks
  without throwing (this is really an assertion embedded in every other task's "happy path"
  test, but confirm it explicitly here too since this task is what makes it a hard gate).
- `EpubExportException`: a project with zero Acts (or all Acts/Chapters filtered to nothing)
  causes `EpubExporter::export()` to throw `EpubExportException`, not silently return a
  broken/empty file.
