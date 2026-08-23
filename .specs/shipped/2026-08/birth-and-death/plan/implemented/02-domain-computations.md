# 02 — Domain computations

## Scope

- `CodexEntryType`: add `inceptionLabel()`, `terminationLabel()`, `tracksLifespan()` (true for all
  three current cases). Beside the existing `label()`/`pluralLabel()` match blocks.
- New value object `App\Support\Age`: `Age::between(CarbonInterface $inception, $moment): self` with
  `->years` (whole years, `diffInYears` floor). Single home for the age format.
- `CodexEntry` computed methods:
  - `hasInvertedLifespan(): bool` — both links set and termination before inception.
  - `ageAt(?Event $moment): ?Age` — null when no inception event, no moment, or inverted; else
    `Age::between(...)`.
  - `existsAt(?Event $moment): bool` — the existence window: true when moment null, type does not
    track lifespan, lifespan inverted, or `inception <= moment <= termination` (each bound
    inclusive, an unset bound open).

Does **not**: touch the resolver, controller, requests, or views (tasks 04–06). No "not yet" /
"gone" labels — they do not exist in this design.

## Depends on

01 (relations feed `ageAt`/`existsAt`).

## Key decisions

- Per-type gate lives on the enum (`open-questions.md` #2). Existence window symmetric + inclusive,
  inverted always-exists (#5, #8). Whole years only (#6).

## Consult

`expanded/architecture.md` → Age computation, `existsAt`; `expanded/data-model.md`.

## Tests

Unit, per `expanded/testing.md` → *Age & existence*:

- `Age`: 1980→2000 = 20; 1980-06→2000-01 = 19 (floor).
- `ageAt`: null on no inception / no moment / inverted; correct years otherwise.
- `existsAt`: before inception false; at inception inclusive true; between true; at termination
  inclusive true; after termination false; no links true; one-sided open; inverted true; a
  non-tracking type true.
- `inceptionLabel`/`terminationLabel` per case; `tracksLifespan` true for all three.
