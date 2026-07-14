# 02 — `SceneReferenceMatcher` service

## Scope

The whole-word, case-sensitive, Unicode-aware matching engine, as a standalone service with no
controller wiring yet. This is the third `app/Services` class (after `AttributeTimeline`,
`CodexMediaService`/`CodexAsOfResolver`) — a reusable, non-trivial domain workflow.

**Builds:**
- `App\Services\SceneReferenceMatcher`:
  - `syncScene(Scene $scene): void` — resolve every codex entry (name + all eligible aliases) in
    the scene's project that whole-word-matches `$scene->contents`, and
    `$scene->codexReferences()->sync($matchedEntryIds)`. Full resync, not incremental.
  - `syncProject(Project $project): void` — for every scene in the project, do the same
    (internally reuses the per-project candidate map/regex it builds once, not once per scene).
- The matching internals (private to the service):
  - Build a **candidate map** for a project: exact-case term → `array<int, int>` of
    `codex_entry_id` (supports duplicate alias/name text across entries — see
    `../plan/00-overview.md` binding decisions). **Never lowercase either side** — matching is
    case-sensitive. Load entries with `->with('aliases')` (one query for entries, one for all
    their aliases) to avoid an N+1 while building the map.
  - **Filter out aliases shorter than 3 characters** before adding them to the map (`mb_strlen`,
    Unicode-safe). `name` has no such floor and is always added.
  - Build **one combined regex** per project: alternation of `preg_quote()`d terms, wrapped in a
    single capturing group, with Unicode-aware whole-word lookaround
    (`(?<![\p{L}\p{N}])...(?![\p{L}\p{N}])`, `u` modifier only — **no `i` flag**, matching is
    case-sensitive). Order of alternatives does not matter for correctness (see
    `../expanded/architecture.md` — boundaries alone prevent shadowing); don't add length-sorting.
  - Run `preg_match_all($pattern, $scene->contents, $matches, PREG_OFFSET_CAPTURE)` and map each
    matched substring **exactly as matched, no case-folding** back through the candidate map to
    the set of entry ids.
  - Empty/null `contents` → empty match set (no error).
  - **Normalize to NFC before matching:** run every candidate term (`name`/`alias`, after the
    length filter) and `$scene->contents` through `Normalizer::normalize($value, Normalizer::FORM_C)`
    (`ext-intl`) before building the regex / running it. Do this once per project build, not
    per-scene inside `syncProject()`'s loop for the candidate side.
  - **Malformed UTF-8 guard:** `preg_match_all(..., 'u')` returns `false` (not `0`) when
    `$scene->contents` isn't valid UTF-8 after normalization. Check for `false` explicitly, log
    a warning with the scene id via `Log::warning`, and treat it as "no matches" for that scene
    — never throw, never block the caller's save.

Also declares `"ext-intl": "*"` in `composer.json`'s `require` (it was previously an undeclared,
environment-provided extension — this task is what starts actually depending on it).

**Does NOT build:** any call site (tasks 03/04), any UI (05/06).

## Depends on

- **01** (`Scene::codexReferences()`, the pivot) in `plan/implemented/`.

## Key decisions already made

- Full `sync()` on every call — never attach-only or diff-based patching. This is the invariant
  `00-overview.md` calls out; a test in this task must catch an attach-only regression (seed a
  stale pivot row directly, call `syncScene()`, assert the stale row is gone).
- Match terms = entry `name` **and** every alias (both count).
- No model hook — this is a plain service class, constructed with no state tied to one
  entry/attribute pair (unlike `AttributeTimeline`), callable directly from a future seeder if
  ever needed without `WithoutModelEvents` interference.
- Case-sensitivity, the 3-character alias floor, Unicode boundaries, and hyphen-as-word-character
  are all binding — see `../expanded/architecture.md` → *Matching rule* for the exact regex
  construction.

## Consult

`../expanded/architecture.md` (the full matching-rule writeup this task implements verbatim),
`../expanded/data-model.md`, `00-overview.md`.

## Also in this task

Add a short **"Scene references"** subsection under the existing *Codex* section of
`documentation/architecture.md`, alongside the `AttributeTimeline`/`CodexMediaService`
descriptions: what `SceneReferenceMatcher` does, the full-resync invariant, and a pointer to why
it's a service and not a hook (mirrors the existing `[!IMPORTANT]`/`[!WARNING]` callout style
used elsewhere in that file).

## Tests (`tests/Unit/SceneReferenceMatcherTest.php`)

- Whole-word match: alias `Mel` matches "Mel said hello" but not "melody" (the canonical
  regression case from the source spec).
- Matches the entry's `name` as well as its aliases.
- **Case-sensitive:** an entry named "Luck" does not match the lowercase noun "luck" in text; it
  does match the exact-case "Luck". "MEL" (wrong case) does **not** match alias "Mel".
- **Alias length floor:** an alias shorter than 3 characters (e.g. "Al") never matches even when
  it appears as an isolated whole word; an entry's `name` of the same short length still matches
  (no floor on `name`).
- Unicode: "Mélusine" doesn't match inside "Mélusines"; punctuation-adjacent match still fires
  (`"Mélusine," she said`).
- Hyphenated alias "Jean-Luc" matches as one unit; "Jean" alone does not match inside it.
- Two entries in the same project with identical alias/name text: both link.
- No cross-project matching: an entry in project A never links a scene in project B even with
  identical alias text.
- Full-resync regression: seed a stale `scene_codex_entry` row that no longer matches, call
  `syncScene()`, assert the stale row is gone (not just that new matches were added).
- Null/empty `contents` → no matches, no error.
- `syncProject()` updates every scene in the project in one call (assert on 2+ scenes at once).
- **NFC normalization:** an alias stored/typed in NFC form matches scene contents containing the
  visually-identical NFD-encoded form of the same text, and vice versa (construct both forms
  explicitly in the test, e.g. via `Normalizer::normalize(..., Normalizer::FORM_D)`, rather than
  relying on how the test file itself happens to be encoded on disk).
- **Malformed UTF-8:** a scene with an invalid UTF-8 byte sequence in `contents` does not throw;
  `syncScene()` completes, the scene ends up with zero references, and a warning is logged
  (assert via `Log::shouldReceive('warning')` or equivalent, not just "it didn't crash").
