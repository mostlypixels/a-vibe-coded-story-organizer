# Architecture — Alias references v1

## New service: `App\Services\SceneReferenceMatcher`

This is the third `app/Services` class (after `AttributeTimeline`, `CodexMediaService`,
`CodexAsOfResolver`) — a "reusable, non-trivial domain workflow" per `CLAUDE.md`. It owns the
whole-word, case-insensitive matching rule in exactly one place, called from two triggers:

```php
class SceneReferenceMatcher
{
    // Recompute one scene's references against every codex entry in its project.
    public function syncScene(Scene $scene): void;

    // Recompute every scene in a project against the current alias/name set —
    // called when a codex entry's aliases (or name) change.
    public function syncProject(Project $project): void;
}
```

> [!NOTE]
> **Not the same thing as the codex index page's name-or-alias search.**
> `CodexEntryController::index` already does a name-or-alias lookup (`->orWhereHas('aliases',
> fn ($aliases) => $aliases->where('alias', 'like', '%'.$search.'%'))`) — a SQL `LIKE` substring
> match, case-insensitive by the DB's default collation, used to help a writer *find* an entry.
> `SceneReferenceMatcher` is a different algorithm solving a different problem (does this exact
> term appear as a whole, case-sensitive word in this prose) and must stay separate — don't
> "unify" the two into one shared matching utility; their semantics are deliberately different.

### Matching rule

- Build one **candidate map** per project: every `CodexEntry`'s `name` plus every
  `CodexAlias::alias` (**both count** — confirmed in grilling: a scene mentioning an entry's
  bare name links it even if that name was never separately added as an alias, matching how the
  codex index search box already treats name and alias as equivalent), keyed by
  **exact-case term → array of `codex_entry_id`** (a `[]`, not a single id — nothing stops two
  entries in the same project from sharing an identical alias/name string, and per the "both
  entries link independently" decision on overlapping aliases, both must resolve when that
  happens).
- **Aliases shorter than 3 characters are excluded from the candidate map entirely** — a
  confirmed decision to cut down false positives from short aliases colliding with ordinary
  words. This applies to aliases only; an entry's `name` is always eligible regardless of length
  (there's no shorter fallback for the primary identifier). A 1–2 character alias is simply
  never matched — this is not a validation rule (short aliases can still be saved and displayed,
  they just never drive a reference link).
- "Whole word" = matched via regex word boundaries, **Unicode-aware** (`\b` in PCRE is ASCII-only
  by default — use `preg_quote($term, '/')` wrapped in `(?<!\p{L}\p{N})` / `(?!\p{L}\p{N})`
  lookaround, or the `u` modifier). Hyphens are treated as part of the word, not a boundary
  (confirmed: "Jean-Luc" matches as one unit, "Jean" alone does not match inside it).
- **Case-sensitive** (no `i` flag) — confirmed decision: a character named "Luck" must not match
  the common noun "luck". The candidate map is keyed by the term's stored case exactly as
  written; do not lowercase either side of the comparison. (This reverses an earlier draft of
  this doc that assumed case-insensitive matching — case-insensitive was never actually grilled
  as its own decision until this pass.)
- Applied against `Scene::contents` **raw Markdown source**, not `renderedContents()` — matching
  markdown syntax characters around a name (e.g. `**Mélusine**`) should still hit, and running
  the regex on rendered HTML risks matching inside tag attributes.
- One project can have many entries × many aliases; build a **single combined regex per project**
  (alternation of all quoted terms) and run it once per scene with `preg_match_all`, rather than
  looping every term against every scene — this is the perf lever `overview.md` flags. Alternative
  order does **not** matter for correctness: because every alternative is independently bounded
  by the same whole-word lookaround, a shorter term (e.g. "Mel") can never partially match inside
  a longer one (e.g. "Melusine") regardless of which comes first in the alternation — the boundary
  check alone prevents the shadowing that an unbounded substring match would risk. Don't sort by
  length; it's unnecessary complexity.
- To resolve a match back to entry ids without per-term named capture groups (which risks PCRE's
  named-subpattern limits on large projects), wrap the whole alternation in **one** capturing
  group, iterate `preg_match_all(..., PREG_OFFSET_CAPTURE)` results, and look up each matched
  substring **exactly as matched (no case-folding)** in the candidate map built alongside the
  regex — since matching is case-sensitive, the matched substring is always the term's exact
  stored text, never a differently-cased variant.
- **N+1 guard when building the candidate map:** load a project's entries with
  `$project->codexEntries()->with('aliases')->get()` (one query for entries, one for all their
  aliases) rather than accessing `->aliases` per entry in a loop, which would N+1.
- **Unicode normalization to NFC before matching.** An accented character (e.g. "é") can be
  encoded as one precomposed codepoint (NFC) or as a base letter plus a combining diacritic
  (NFD) — visually identical, different bytes. A French/Italian alias typed on one platform and
  scene text pasted from another (macOS text fields, some Word/PDF exports commonly produce NFD)
  can silently fail to match with byte-exact comparison even though a human sees no difference.
  Confirmed fix: normalize **both** every candidate term and `Scene::contents` to
  `Normalizer::NFC` (`ext-intl`) once, before building/running the regex. `ext-intl` is present
  in this project's runtime but was **not yet declared** in `composer.json` — this task must add
  `"ext-intl": "*"` to `require`.
- **Malformed UTF-8 handling.** `preg_match_all` with the `u` modifier returns `false` (a hard
  failure, not "zero matches") when its subject isn't valid UTF-8 — this can happen from mixed-
  encoding paste even in an otherwise-plain-text field. `syncScene()`/`syncProject()` must check
  for `false` explicitly and log a warning (`Log::warning` with the scene id) rather than let a
  malformed scene silently end up with zero references and no signal to the writer. This is not
  a fatal error — the scene save itself must still succeed; only the reference sync degrades.

### Where each trigger calls it

- `SceneController::store` / `SceneController::update` (`app/Http/Controllers/SceneController.php`):
  after `$scene->update(...)`/`create(...)`, call `$matcher->syncScene($scene)`. Single-scene
  scope — cheap, matches the existing "everything happens on Save" flow (mirrors
  `mentionedEvents()->sync(...)` right below it).
- `CodexEntryController::store`: a new entry's alias/name set is by definition new (there is no
  "before" state), so it **always** calls `$matcher->syncProject($project)` after
  `syncAliases(...)` — this is not subject to the "only if changed" skip below, since "changed"
  is trivially true for every create.
- `CodexEntryController::update`: call `$matcher->syncProject($project)` **only if** the alias
  set or name actually changed — compare before/after inside the transaction to avoid a
  full-project rescan on every unrelated edit (e.g. saving only a new cover image). This is the
  expensive path: O(scenes) work triggered by a single entry save.

  > [!NOTE]
  > Do the rescan **inside the same `DB::transaction`** as the alias sync (both are DB-only
  > writes — no disk I/O), consistent with the existing post-commit-disk-only pattern in this
  > controller. This keeps "alias saved" and "references recomputed" atomic.

### Why not a model `booted()` hook

`CLAUDE.md`'s "logic in models" carve-out is for *invariants and lifecycle* (position
assignment, main-plotline creation), not *application workflow*. This is workflow: it needs to
compare old vs. new alias state (not just "a save happened") and — for the project-wide
rescan — touches records well outside the model being saved. A hook can't cleanly express "only
rescan if aliases changed" without reaching back into controller-validated data. It also matches
the `WithoutModelEvents` seeding caveat: a service the seeder can call directly (like
`AttributeTimeline`) rather than a hook that silently no-ops under seeding.

### Import/export interaction

**Export writes nothing.** `StaticSiteExporter::addScene()` builds `scene.json` from an explicit
field list (`id`, `name`, `position`, `status`, `chapter_id`, `event_id`,
`mentioned_event_ids`, plus field-file links) — `scene_codex_entry` is simply never touched,
the same "absence by construction" the share-link columns already demonstrate elsewhere in that
method. No code change is needed in the exporter; the only risk is someone adding
`codex_entry_ids` to that field list later out of a sense that "everything should be exported" —
`documentation/export-format.md` now has an explicit `[!NOTE]` warning against that.

**Import regenerates once, after the Codex phase.** `ProjectGraphImporter` runs four phases in
order — `Project → Timeline → Story → Codex` (`ProjectImporter::GRAPH_PHASES`) — and both halves
the matcher needs only exist after the last one: scenes (with their final `contents`) are
created in the **Story** phase, codex entries and their aliases in the **Codex** phase. So the
resync cannot run as part of, or between, any of the four existing checkpointed phases — it must
run once the whole graph is on disk.

`ProjectImporter::run()`'s loop (`app/Services/ProjectImporter.php`) already has the right shape
for this without touching the `ImportPhase` enum or the checkpoint contract: it iterates
`remainingPhases($import->phase)`, and when an import resumes from an already-committed Codex
phase, that loop is empty — `run()` falls straight through to
`$import->update(['phase' => ImportPhase::Completed])`. That fall-through point (after the loop,
before marking `Completed`) is reached **exactly once per import that finishes**, on any run() invocation,
whether the whole thing runs in one call or several resumes — so it's the correct hook:

```php
// after the foreach ($this->remainingPhases(...) as $phase) { ... } loop, before:
$this->matcher->syncProject($import->project);
$import->update(['phase' => ImportPhase::Completed]);
```

- `syncProject()` is idempotent (full resync — see the *Invariant* in `data-model.md`), so if
  this call itself throws and the import is resumed, retrying it is always safe — no new state
  needs to be tracked for it, unlike the four real graph phases.
- It runs **outside** any of the four phases' own `DB::transaction` blocks (each already
  committed), reading committed data — no different from how a normal scene/entry save's sync
  runs after its own commit.
- No new `ImportPhase` enum case, no new checkpoint field. This is deliberately **not** a fifth
  graph phase — it has no id-map to accumulate and nothing in the archive maps to it.

### Project/user deletion — no purge hook needed (unlike media)

Every step from `projects` down to `scenes` and `codex_entries` is **already** a DB-level
`cascadeOnDelete` FK chain (`projects.id → acts.project_id → chapters.act_id → scenes.chapter_id`,
and `projects.id → codex_entries.project_id`) — confirmed directly in the migrations, not
assumed. Deleting a `Project` (or, via `User::deleting`'s `$user->projects->each->delete()`,
every project a deleted user owns) therefore cascades all the way to `scenes` and
`codex_entries` at the database level, and our own `scene_codex_entry.{scene_id,codex_entry_id}`
`cascadeOnDelete` (task 01) rides along automatically — **no new purge hook is needed**, unlike
`CodexMediaService::purgeProject()`, which exists specifically because *disk files* aren't
covered by a DB cascade. Do not add a `scene_codex_entry`-specific cleanup hook anywhere; it
would be redundant and risks drifting from the DB-level truth.

### Never appears on the public scene share page

`SharedSceneController@show` renders a **separate** template (`shared/scenes/show.blade.php`),
not `scenes/edit.blade.php`'s sidebar — the "Codex references" card (task 06) lives only in the
authenticated edit page and must never be added to the public view. This isn't automatic the way
the export/cascade cases above are: a future edit to the public template could plausibly add a
"see also" section without realizing codex entries can carry spoiler/worldbuilding content never
meant for a public link recipient (the same reasoning that already keeps `notes` off that page).
Treat "no codex references on the public page" as binding as the existing "`notes` is private"
rule — see `00-overview.md` invariants and `testing.md`'s regression test for it.

### Read paths (no new service needed — thin controller additions)

- **Codex edit sidebar** ("references in scenes, in timeline order"): add to
  `CodexEntryController::edit` —

  ```php
  'referencingScenes' => $codexEntry->referencingScenes()
      ->with('chapter.act', 'event')
      ->get()
      ->sortBy(fn (Scene $scene) => [$scene->event?->event_datetime, $scene->event?->id ?? PHP_INT_MAX])
      // see open-questions.md — ordering key for scenes with no assigned event
  ```

  This reuses the canonical `(event_datetime, id)` ordering rule from `architecture.md`'s
  Codex section (`AttributeTimeline`) rather than inventing a new "timeline order" — everywhere
  else in the app that means the event timeline, not manuscript position.

- **Scene edit sidebar** ("codex relationships"): add to `SceneController::edit` —

  ```php
  'referencedEntries' => $scene->codexReferences()->with('cover')->orderBy('type')->orderBy('name')->get()
  ```

  Grouped by `type` in the Blade view the same way `CodexAsOfResolver`'s output already groups
  by type (reuse the visual pattern, not necessarily the service — this is a flat eager-loaded
  list, no timeline resolution needed).

Both are read-only eager loads added to an existing `edit()` action — no new routes, no new
controllers, following the "controllers stay thin, delegate" convention.

## Authorization

No new authorization surface: both read paths hang off `CodexEntryController::edit` and
`SceneController::edit`, which already authorize via `$this->authorize('update', ...->project)`
before loading any data. The pivot table itself is never queried across a project boundary
because both eager-load calls start from an already-authorized `$codexEntry` / `$scene`.
