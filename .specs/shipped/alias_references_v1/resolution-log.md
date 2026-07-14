# Alias references v1 — resolution log

The running record of feedback/decisions, deviations from the spec/plan, and issues →
resolutions found while implementing and verifying this feature. The `plan-implementer` agent
appends here per task; `ship-plan` consolidates it. Read it before extending the feature.

## Feedback & decisions

### Task 04 — change-detection compares terms case-SENSITIVELY, not lowercased

The task file said to capture the alias set "(lowercased, sorted)" for the update change-check.
Implemented it **case-sensitively** instead (see `CodexEntryController::referenceTerms()`): matching
itself is case-sensitive (a character named "Luck" must not match the noun "luck"), so a case-only
edit ("Luck" → "luck") genuinely changes which scenes match and must still trigger a rescan.
Lowercasing the comparison would treat that edit as "unchanged" and silently skip the recompute,
leaving stale/incorrect pivot rows — a violation of the full-resync-correctness invariant. The
"lowercased" wording was read as carried over from the codex *index* search (which is deliberately
case-insensitive), not authoritative over the binding case-sensitivity decision. Name and aliases
are combined into a single sorted set for the comparison: swapping which field carries a given
string leaves the union of terms unchanged and therefore cannot change any match, so no rescan is
needed in that case — combining is both correct and slightly more precise than comparing the two
fields separately.

## Deviations from the spec/plan

### Task 02 — hyphen added to the whole-word boundary class

The spec snippets in `architecture.md`/the task file wrote the boundary lookaround literally as
`(?<![\p{L}\p{N}])...(?![\p{L}\p{N}])` (no hyphen), yet the **binding decision** right next to it
says "Hyphens are part of the word, not a boundary — 'Jean-Luc' matches as one unit, 'Jean' alone
does not match inside it", and `testing.md`/the task lists a test asserting exactly that. Those two
are contradictory: with hyphen *outside* the class, a separate entry named "Jean" **would** match
inside "Jean-Luc" (a hyphen would count as a boundary). Implemented the binding decision — the
boundary class is `[\p{L}\p{N}\-]` — so a bare segment never matches across a hyphen. The literal
regex snippet was treated as an incomplete illustration, not authoritative over the decision + its
test. `SceneReferenceMatcher::buildCandidates()` documents this inline.

## Issues → resolutions

### Task 01 — pre-existing unrelated suite failure

`composer test` shows one red test, `tests/Unit/SpecsStatusConsistencyTest`, failing on
`.specs/draft/advanced_search/spec.md has no status: frontmatter`. Root cause: an empty,
untracked `advanced_search` draft spec that predates this task (present in git status at
session start), unrelated to alias references. Left untouched — out of scope for this
feature. All 499 other tests pass, including the 3 new `ScenePivotTest` cases.

### Task 01 — pivot test needs a booted app, so it extends `Tests\TestCase`

The task suggested `tests/Unit/ScenePivotTest.php`, but the existing `tests/Unit/*`
tests extend the bare `PHPUnit\Framework\TestCase` (no DB). A pivot round-trip needs the
framework + in-memory SQLite, so `ScenePivotTest` extends `Tests\TestCase` with
`RefreshDatabase` (feature-test style) while living under `tests/Unit/` per the task's
named path. `SceneReferenceMatcherTest` (task 02) follows the same choice for the same reason.

### Task 02 — same pre-existing `advanced_search` draft-spec failure persists

`composer test` still shows the one unrelated red test (`SpecsStatusConsistencyTest`, the
untracked `.specs/draft/advanced_search/spec.md` with no `status:` frontmatter) documented under
task 01. Left untouched — out of scope. 512 tests pass including the 13 new
`SceneReferenceMatcherTest` cases (30 assertions).

### Task 03 — same pre-existing `advanced_search` draft-spec failure persists

`vendor/bin/phpunit` still shows the one unrelated red test (`SpecsStatusConsistencyTest`, the
untracked `.specs/draft/advanced_search/spec.md` with no `status:` frontmatter) documented under
tasks 01/02. Left untouched — out of scope. 516 tests pass including the 4 new `SceneTest`
cases wiring `SceneReferenceMatcher::syncScene()` into `store`/`update`. `pint --dirty` clean.

### Task 04 — same pre-existing `advanced_search` draft-spec failure persists

`vendor/bin/phpunit` still shows the one unrelated red test (`SpecsStatusConsistencyTest`, the
untracked `.specs/draft/advanced_search/spec.md` with no `status:` frontmatter) documented under
tasks 01–03. Left untouched — out of scope. 520 of 521 tests pass, including the 5 new/extended
`CodexEntryTest` cases wiring `SceneReferenceMatcher::syncProject()` into `store`/`update`.
`pint --dirty` clean.

### Task 04 — initial removal-test setup didn't establish the link (seeded alias + unchanged save)

First draft of `test_editing_an_entry_so_it_no_longer_matches_removes_the_row` seeded the entry's
matching alias directly in the DB (no matcher run → no pivot row), then PUT the same alias back.
Because the update change-detector saw identical terms before/after, it correctly skipped the
rescan — so the pivot row was never created and the "row exists" pre-assertion failed. Root cause:
the test relied on a save to *establish* a link while giving that save nothing to change. Fixed by
seeding the entry with **no** alias and having the first PUT *add* `Mel` (a real term change → the
rescan runs and links the scene), then a second PUT swaps it away to prove removal. This is itself
a useful confirmation that the "only rescan on change" guard behaves as intended.

### Task 05 — same pre-existing `advanced_search` draft-spec failure persists

`composer test` still shows the one unrelated red test (`SpecsStatusConsistencyTest`, the
untracked `.specs/draft/advanced_search/spec.md` with no `status:` frontmatter) documented under
tasks 01–04. Left untouched — out of scope. 524 of 525 tests pass, including the 4 new
`CodexEntryTest` cases for the edit-page "Referenced in scenes" sidebar (help text, event-timeline
order, unassigned-scene-last-and-labelled, empty state). `pint --dirty` clean. The sidebar is
server-rendered Blade with no new JS/CSS; the new feature tests GET the real `codex.edit` route and
assert the rendered help text, scene links, ordering, and label — direct observation of the
rendered surface. No stale `public/hot`; `@vite` serves `public/build/assets`.

## Feedback & decisions (continued)

### Task 05 — unassigned-scene sort key and distinct label beyond the spec snippet

`architecture.md`'s illustrative `sortBy` key (`[$scene->event?->event_datetime, $scene->event?->id
?? PHP_INT_MAX]`) would sort event-less scenes *first* (null sorts before real datetimes in
`sortBy`), contradicting the binding decision "unassigned scenes sort last". Implemented the
full ordering the task file spells out instead: a leading `event === null ? 1 : 0` flag forces all
unassigned scenes after every assigned one, then `(event_datetime->timestamp, event_id)` orders the
assigned group and `(act.position, chapter.position, position)` tiebreaks the unassigned group
(`CodexEntryController::referencingScenesInTimelineOrder()`). The `ui.md` Blade snippet showed only
`@if ($scene->event)` with no else; added an `@else` "No event assigned" label so unassigned scenes
are distinctly marked rather than silently missing their event line, per the task's explicit
requirement.

### Task 06 — same pre-existing `advanced_search` draft-spec failure persists

`vendor/bin/phpunit` still shows the one unrelated red test (`SpecsStatusConsistencyTest`, the
untracked `.specs/draft/advanced_search/spec.md` with no `status:` frontmatter) documented under
tasks 01–05. Left untouched — out of scope. 529 of 530 tests pass, including the 5 new `SceneTest`
cases for the edit-page "Codex references" sidebar (entry links + type label, last-save caption,
empty state, non-owner 403, and the public-share-page-never-leaks regression). `pint --dirty` clean.
The card is server-rendered Blade with no new JS/CSS; `public/build/manifest.json` exists and no
`public/hot` is present, so `@vite` serves the built assets. The new feature tests GET the real
`scenes.edit` route and assert the rendered heading, entry name, type label, `codex.edit` link, and
caption — direct observation of the rendered surface.

### Task 02 — `Normalizer::normalize()` on malformed UTF-8 returns false without a PHP warning

Verified directly that intl's `Normalizer::normalize()` returns `false` (not the string) on an
invalid UTF-8 byte sequence and does **not** emit a PHP warning, so the malformed-UTF-8 guard
catches it at the normalization step (before `preg_match_all` is even reached) and needs no `@`
suppression that could otherwise turn into a PHPUnit failure. Either guard (`normalize()` → false,
or `preg_match_all(..., 'u')` → false) logs one `Log::warning` with the `scene_id` and returns an
empty match set; the test asserts the warning fires exactly once via `Log::shouldReceive`.

## Task 07 — Import regeneration

### Deviations

- **Corrected a stale doc pointer (not a spec change).** The task said to verify — not
  duplicate — the `[!NOTE]` in `documentation/export-format.md` warning against exporting
  `codex_entry_ids`. The note was present but pointed junior devs at
  `ProjectGraphImporter::importCodex()` as "where the recomputation happens after import", which is
  wrong: regeneration lives in `ProjectImporter::run()` (a `SceneReferenceMatcher::syncProject()`
  call after the graph phases), not inside the Codex graph phase. Repointed the note to the actual
  hook so the doc doesn't send readers to the wrong file. No behavioural change.

### Feedback & decisions

- **Regeneration hook placement matches the spec exactly.** `SceneReferenceMatcher` was injected as
  a third constructor dependency of `ProjectImporter`, and `syncProject($import->project)` is called
  once after the `remainingPhases()` loop and before `$import->update(['phase' => Completed])`. No
  `ImportPhase` case, no checkpoint field, no new transaction — it reads already-committed data and
  is a full idempotent resync, so a crash between it and `Completed` retries safely on the next
  `run()`. Confirmed the same file, `ProjectImporter.php`, and left `ProjectGraphImporter`
  untouched.

### Issues → resolutions

- **Resumability/failure tests must not trip `run()`'s re-extraction.** `run()` re-extracts from the
  stored zip whenever the extraction directory is missing (`! is_dir($dataPath)`). The two
  constructed-state tests (resume-after-Codex, fail-before-Codex) have no real archive on disk, so
  they pre-create the extraction directory via `Storage::disk('local')->makeDirectory(...)` with an
  `archive_path` whose `.zip`-stripped form matches it. That lets `run()` skip re-extraction and
  reach the phase loop directly — for the resume case an empty `remainingPhases(Codex)` loop that
  falls straight through to the regeneration hook, proving the hook fires on a resumed call; for the
  failure case a mocked `ProjectGraphImporter::importStory()` that throws before Codex, proving no
  `scene_codex_entry` rows are written when the hook is never reached (`assertDatabaseCount(0)`).
  Root cause: the derived-cache hook is unreachable to a naive constructed `Import` unless the
  on-disk extraction invariant `run()` depends on is satisfied first.
