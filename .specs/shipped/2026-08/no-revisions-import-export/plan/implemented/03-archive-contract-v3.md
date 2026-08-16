# 03 — Archive contract goes v3

## Scope

- `StaticSiteExporter::DATA_VERSION` → **3**, with its docblock explaining the bump (a removed
  manifest key is a layout removal under the version contract).
- `ImportRules::SUPPORTED_MANIFEST_VERSIONS` → **`[3]`**. Update the constant's docblock: the list
  is still the one-line opt-in for a future format, it just holds one entry now.
- `ImportRules::isAllowedPath()` — reject any entry whose path contains a `revisions/` segment,
  before the allow-list prefixes are consulted.

**Not** in scope: `RevisionOrigin`/`RevisionPurger` (task 04), documentation (task 05).

## Depends on

01 and 02. Bumping the version while either side still writes or reads `includes_revisions` leaves
the suite asserting two contradictory contracts.

## Key decisions

- **v1 and v2 archives are rejected**, at `ArchiveValidator`'s check 4. Pre-V1, nobody holds an
  archive they cannot re-export.
- `ImportValidationException::unsupportedManifestVersion()`'s message is **unchanged** — no
  re-export hint.
- **Reject `revisions/`, don't ignore it.** No v3 export can produce one, so a zip carrying them is
  malformed by definition. Without the rule they sit under the `data/acts/` / `data/timeline/` /
  `data/codex/` / `data/project/` allow-listed prefixes and get `extractTo()`'d onto disk by
  `ProjectImporter::extract()` — the validator reads into memory, but the import phase extracts the
  whole zip.
- No `ArchiveValidator` code change: `MANIFEST_REQUIRED_KEYS` never listed `includes_revisions`, and
  `validateDescriptors()` only decodes known descriptor basenames, so a sidecar was never
  JSON-checked. The rejection lives entirely in `ImportRules`.
- Keep the rule narrow — a path *segment* named `revisions`, not a substring match that would also
  catch an act legitimately slugged `…-revisions`.

## Tests

- **Bump five fixtures** from `'version' => 1`/`2` to `3`: `tests/Feature/ImportTest.php` (×2),
  `tests/Unit/Import/ArchiveValidatorTest.php`, `tests/Unit/Import/ProjectGraphImporterTest.php`,
  `tests/Unit/Import/ProjectImporterTest.php`. Missing one turns a passing test into a rejection.
- `ArchiveValidatorTest` — **new**: a version 1 archive is rejected; a version 2 archive is
  rejected. (The `version => 999` case already exists; these state the compat drop as a fact rather
  than an accident of a constant.)
- `ArchiveValidatorTest` — **new**: a v3 zip carrying `.../revisions/contents.json` fails check 3.
- **New**: an act or codex entry whose slug ends in `revisions` still imports — the guard against an
  over-broad match.

## Consult

`expanded/architecture.md` → *3. The archive contract* · `expanded/open-questions.md` items 1, 4, 7.
