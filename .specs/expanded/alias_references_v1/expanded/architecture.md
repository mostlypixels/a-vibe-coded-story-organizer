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

### Matching rule

- Build one **candidate list** per project: every `CodexEntry`'s `name` plus every
  `CodexAlias::alias`, each tagged with its owning `codex_entry_id`.
- "Whole word" = matched via regex word boundaries, **Unicode-aware** (`\b` in PCRE is ASCII-only
  by default — use `preg_quote($term, '/')` wrapped in `(?<!\p{L}\p{N})` / `(?!\p{L}\p{N})`
  lookaround, or PHP's `u` modifier with `\b`, whichever survives the "Mel"/"melody" test case
  from the source spec plus accented names like "Mélusine" not matching inside "Mélusines"-plural).
  Confirm the exact boundary definition in `open-questions.md` — this is the one rule with real
  edge-case risk (apostrophes, hyphenated names, plurals).
- Case-insensitive (`i` flag).
- Applied against `Scene::contents` **raw Markdown source**, not `renderedContents()` — matching
  markdown syntax characters around a name (e.g. `**Mélusine**`) should still hit, and running
  the regex on rendered HTML risks matching inside tag attributes.
- One project can have many entries × many aliases; build a **single combined regex per project**
  (alternation of all quoted terms, longest-first so multi-word aliases don't get shadowed by a
  shorter overlapping one) and run it once per scene with `preg_match_all`, rather than looping
  every term against every scene — this is the perf lever `overview.md` flags. Map each matched
  term back to its `codex_entry_id` via the candidate list built alongside the regex.

### Where each trigger calls it

- `SceneController::store` / `SceneController::update` (`app/Http/Controllers/SceneController.php`):
  after `$scene->update(...)`/`create(...)`, call `$matcher->syncScene($scene)`. Single-scene
  scope — cheap, matches the existing "everything happens on Save" flow (mirrors
  `mentionedEvents()->sync(...)` right below it).
- `CodexEntryController::store` / `CodexEntryController::update`
  (`app/Http/Controllers/CodexEntryController.php`): after `syncAliases($entry, ...)` (and the
  `name` update, already in `$codexEntry->update([...])`), call `$matcher->syncProject($project)`
  **only if** the alias set or name actually changed — compare before/after inside the
  transaction to avoid a full-project rescan on every unrelated edit (e.g. saving only a new
  cover image). This is the expensive path: O(scenes) work triggered by a single entry save.

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
