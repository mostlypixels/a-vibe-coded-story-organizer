# Data model — Alias references v1

## New table: `scene_codex_entry`

Plain pivot, following the `codex_entry_tag` convention (`database/migrations/2026_07_04_000005_create_codex_entry_tag_table.php`):

```php
Schema::create('scene_codex_entry', function (Blueprint $table) {
    $table->foreignId('scene_id')->constrained()->cascadeOnDelete();
    $table->foreignId('codex_entry_id')->constrained()->cascadeOnDelete();
    $table->primary(['scene_id', 'codex_entry_id']);
});
```

- No `id`, no timestamps — matches `codex_entry_tag` (a pure derived link table has no
  identity or audit value of its own).
- `cascadeOnDelete` on both FKs: deleting a `Scene` or a `CodexEntry` cleans the pivot for
  free at the DB level (mirrors how `codex_entry_tag` behaves on entry delete; scenes already
  cascade this way for `mentioned_events` via the `event_scene` — style-check that table's
  migration for the exact pattern if it differs).
- No extra columns (e.g. which alias matched, match count). See `open-questions.md` — v1 only
  needs "does a link exist", not provenance, per the spec's plain "save the association" wording.

## Model relations

`Scene` gains:

```php
public function codexReferences(): BelongsToMany
{
    return $this->belongsToMany(CodexEntry::class, 'scene_codex_entry');
}
```

`CodexEntry` gains:

```php
public function referencingScenes(): BelongsToMany
{
    return $this->belongsToMany(Scene::class, 'scene_codex_entry');
}
```

Both are plain `belongsToMany` — no pivot model needed (no extra pivot columns).

## Invariant

**The pivot is a derived cache of "does any alias/name of this entry appear as a whole word in
this scene's contents", recomputed wholesale on each trigger** — never partially patched.
Concretely:

- On `Scene::store`/`Scene::update`: after saving `contents`, resync the *scene's* full set of
  matching entries within its project (`$scene->codexReferences()->sync($matchedEntryIds)`).
- On `CodexEntry::store`/`CodexEntry::update` (i.e. whenever `syncAliases` runs and the alias
  set actually changed, or the entry `name` changed — the name is always matched too, per the
  source spec's "look for mention of all the codex aliases" plus the existing search-by-name
  convention in `CodexEntryController::index`): resync every *scene in the project* against the
  updated matcher set for that project. This is the expensive path — see `architecture.md`.

There is no scenario where a row is added/removed one at a time; every recompute is a full
`sync()` for the affected scope. This keeps the invariant trivially correct (no drift between
"what should match" and "what's stored") at the cost of redoing work that didn't change — the
performance section addresses that cost, not the correctness model.

## Export/import impact

**Never exported.** The pivot is a derived cache (see *Invariant* above) — `StaticSiteExporter`
never writes `scene_codex_entry` to the archive at all (`addScene()`'s `scene.json` has no
`codex_entry_ids` key, and none should be added). It carries no lossless-content obligation the
way `contents`/`description`/`notes` do, because it can always be rebuilt from them.

**Regenerated once on import, after the graph exists.** `ProjectGraphImporter` builds scenes in
its Story phase and codex entries/aliases in its Codex phase (the last of the four); the matcher
needs both, so the resync can only happen after Codex commits — see `architecture.md` → *Import/
export interaction* for exactly where. This means an archive exported **before** this feature
existed (no `scene_codex_entry` concept at all) imports identically to one exported after — the
references are always freshly derived on import, never read from the archive either way.

## Seeding impact

`MelusineSeeder` uses `WithoutModelEvents`, so if the recompute lives in a model `booted()`
hook it will **not** fire during seeding (same caveat as `position` and the main plotline —
see `architecture.md` → *Seeding caveat*). Two options, resolved in `architecture.md`'s
service placement:

- If the matcher is a `Service` invoked explicitly from the controller (not a model hook), the
  seeder can call it directly, same pattern as `AttributeTimeline::ensureBaseline` in
  `MelusineSeeder`.
- This spec **does not require** seeded Melusine data to have references pre-populated: it's
  cosmetic, not an invariant the test suite depends on. Flagged in `open-questions.md` in case
  the user wants the demo data to showcase this feature.
