# Open questions

Each item states the ambiguity and a recommended answer — confirm or override during the
`grilling` pass in `plan-tasks`.

1. **What does "3 columns" mean for the Timeline section, which only has 2 entity kinds
   (Plotlines, Events)?**
   Recommendation: leave the 3rd column slot empty in the grid (keeps all three sections
   visually aligned at a glance) rather than special-casing Timeline to a 2-column layout.
   Confirm this is acceptable, or that a 2-column Timeline row is preferred.

2. **Should Codex results be one column ("Codex entries") or three (one per
   `CodexEntryType`)?**
   Recommendation: three, one per type (Characters / Locations / Organizations), matching the
   literal "3 columns" instruction and mirroring the existing Codex nav dropdown's per-type
   split. Confirm, since the spec's "3 columns" line reads as one global rule applied per
   section rather than a coincidence of Codex happening to have 3 types.

3. **Should `CodexAlias.alias` be searched as part of Codex entries?**
   The spec's field list ("string and text fields of the codex entries...") doesn't explicitly
   name aliases, but aliases exist precisely so a codex entry is findable under alternate
   names elsewhere in the app (`CodexEntry::aliases()`, `SceneReferenceMatcher`).
   Recommendation: out of scope for v1 (ship the literal field list first), but call this out
   explicitly as the most likely fast-follow — a writer searching a nickname will expect a hit.

4. **Should `CodexAttributeValue.value` be searched?**
   Same reasoning as aliases: not in the spec's literal field list, and it's a value tied to a
   specific `CodexEntry` + `CodexAttribute` + point in time (`AttributeTimeline`), which
   doesn't fit the simple "field on an entity" result-row shape used everywhere else.
   Recommendation: out of scope for v1; revisit only if requested.

5. **Highlight color**: Recommendation in `ui.md` is `bg-sun-200` (`#ffe494`). The `sun` scale
   originally skipped `200`; it was filled in during the pre-ship review (along with the other
   palette gaps). Confirmed there isn't already a project-wide "highlight" convention this should
   match instead (searched — none found; the only prior `sun` use is `bg-sun-400` on the table
   header).

6. **Result ordering within a column**: the spec doesn't say. Recommendation: match each
   entity's natural list ordering elsewhere in the app (`position` for Acts/Chapters/Scenes,
   `event_datetime` for Events per `EventController@index`'s default sort, `name`/creation
   order for Plotlines and Codex entries) rather than inventing a relevance score — keeps
   search results predictable and consistent with the rest of the UI, and avoids building
   ranking logic the spec never asked for.

7. **Snippet length**: not specified. Recommendation: ~120 characters of context centered on
   the first match in that field (simple, fast, no config surface) — flag if a fixed
   config value in `config/` is wanted instead (per `CLAUDE.md` § "Configuration should be
   kept in a single place"), though a single hardcoded constant in `SearchSnippet` seems
   proportionate for one tunable number.
