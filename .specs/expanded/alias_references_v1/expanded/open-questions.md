# Open questions — Alias references v1

Each question states the recommended answer first.

> [!NOTE]
> **Resolved** via a light grilling pass on 2026-07-14. All seven questions below were
> confirmed as their stated recommendation, with one scope change: Q4's "queue rescans for
> large projects" question was split out into a separate future spec,
> `.specs/draft/alias_references_asynchronous/spec.md` — v1 stays fully synchronous.

1. **Word-boundary definition for accented/Unicode names.** Recommend: PCRE `u` modifier with
   `\b`-equivalent Unicode boundaries (letters/digits vs. everything else), so "Mélusine" doesn't
   match inside "Mélusines" but does match with straight or curly quotes/punctuation adjacent
   (`"Mélusine," she said`). Confirm this is the intended boundary, and confirm hyphenated
   aliases (e.g. "Jean-Luc") should match as one unit, not split on the hyphen.

2. **Overlapping aliases across two entries.** Recommend: **both entries link** (a substring
   match for entry A inside entry B's alias, e.g. "Mel" inside "Melusine", still fires for A
   whenever A's own alias appears as a separate whole word — but if A's alias "Mel" is not a
   whole word inside "Melusine" itself, it correctly doesn't fire there, per the whole-word
   rule). The help text already warns the user about overlap; the system doesn't need to be
   clever about disambiguating who "really" was meant — just consistent. Confirm this "match
   everything that whole-word-matches, let overlap be the user's problem" stance, rather than a
   longest-match-wins exclusivity rule.

3. **"Timeline order" on the codex sidebar — scenes with no assigned event.** The app has two
   orderings (manuscript position vs. event timeline); "timeline order" per the spec's own
   wording and this app's established vocabulary (architecture.md's Codex section) means event
   order. Recommend: unassigned scenes (no `event_id`) sort **last**, in manuscript order among
   themselves (act/chapter/position) as a stable tiebreak, with a small label ("not yet placed
   on the timeline"). Confirm, or should they be excluded from the list entirely?

4. **Rescan scope when an entry's alias changes.** Recommend: rescan **only that project's**
   scenes (not global) — confirmed by the domain model (aliases only compete for matches within
   one project). Confirm this project-wide (not global) rescan is acceptable performance-wise
   for the expected project size, or whether it should be deferred to a queued job even in
   synchronous-by-default mode (mirroring `ImportSetting`'s `run_in_background` pattern) if
   projects can have hundreds of scenes.

5. **Does saving a codex entry with *no* alias/name change still need a rescan?** Recommend:
   **no** — `architecture.md` proposes comparing before/after alias set and `name` inside the
   transaction, skipping the rescan when unchanged (e.g. only a cover image was replaced).
   Confirm this optimization is wanted, versus always rescanning on every entry save for
   simplicity (accepting the extra cost).

6. **Matching against `Scene.description` or `Scene.notes` too?** The source spec says "the
   text" without specifying which field. Recommend: **`contents` only** — `description`/`notes`
   are rich-HTML metadata, not manuscript prose, and `notes` is explicitly private
   (architecture.md's "never renders `notes`" rule for the public share page — mixing it into a
   feature that surfaces "this scene mentions X" risks leaking private-note content indirectly
   through the reference list). Confirm.

7. **Seed data.** Recommend: don't touch `MelusineSeeder` to pre-populate references — this is
   cosmetic and the seeder already has multiple `WithoutModelEvents` caveats to track. Confirm
   it's fine to leave the seeded demo project without alias references until someone re-saves a
   seeded scene by hand.
