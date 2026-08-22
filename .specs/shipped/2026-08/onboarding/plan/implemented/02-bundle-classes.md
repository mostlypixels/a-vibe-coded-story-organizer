# 02 — Bundle classes and resolver

## Scope

- A shared contract (interface or abstract) for a genre bundle. It exposes the bundle's
  attributes, tags, sample entries, and act/chapter skeleton as plain data.
- One support class per genre in `app/Support` (e.g. `app/Support/Bundles/…`): Contemporary,
  Historical, Fantasy, ScienceFiction. Each returns **thin** placeholder data (1–2
  attributes, 1–2 tags, 0–1 sample entries, a tiny skeleton).
- A resolver from `Genre` to its bundle (e.g. `Genre::bundle()` or a `Bundles::for(Genre)`
  support method). `Blank` resolves to an empty bundle (all lists empty).

Not in scope: applying the data (task 03). No DB writes here — this task is pure data +
resolution, unit-testable without the database.

## Depends on

- 01 (the `Genre` enum).

## Key decisions

- One class per bundle, behind one contract, so the seed action treats them uniformly.
- Sample-entry data carries per-attribute **Start** values, keyed by attribute name.
- Bundles seed **no scenes** in v1 (keeps the reference-matcher out of the seed path).
- Content is placeholder; a later pass fills it. Do not over-invest in the copy.

## Consult

- `expanded/data-model.md` → "Bundle content".
- Mirror `app/Support/PlotlineColors.php` / `app/Support/ThemePreset.php` for shape.

## Tests

- The resolver returns the right class per genre; `Blank` returns empty lists.
- Each bundle's declared `applies_to` values are valid `CodexEntryType` cases.
- Sample-entry attribute keys match names of attributes the same bundle declares.
