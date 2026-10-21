---
status: draft
---

# Improve Matcher

Scene references (`App\Services\SceneReferenceMatcher`) miss codex entries in two cases. The
writer sees a place or a person in the scene text, but not in the scene's "Codex references".

1. **A shorter term hides a longer one.** All names and aliases go into one regex alternation.
   When two terms start at the same word, the first alternative that matches wins. The alias
   `Russelby` (Viscount Russelby) takes the text "Russelby Manor", so the location never links.
   The class docblock says the order of alternatives is irrelevant. That is true for
   boundaries only, not for overlap.
2. **Case.** Matching is case-sensitive by decision (see `documentation/features/codex.md` →
   *Scene references*). Prose writes "chestnut tree" and "Russelby manor", so an entry named
   "Chestnut tree" needs a hand-added lower-case alias.

Found with real data: in the Gardenia Girl import, 4 of 16 locations link to no scene
(Russelby Manor, Farcliffe Manor, Glassford town house, Lady Lydia's house).

## Goals

- A longer name or alias wins over a shorter one that starts at the same position.
- A test that fails before the fix, with an overlapping name and alias.
- Fix the docblock and the codex documentation that say order does not matter.
- Decide how case should work, without bringing back the false positives that the
  case-sensitive rule removed.

## Non-goals

- No fuzzy or stemmed matching (plurals, possessives beyond what a word boundary allows).
- No change to when the matcher runs (save, blur, Ctrl+S) or to the batching.
- No change to the entry search, which is case-insensitive on purpose.
- No UI to pick or reject a single match.

## Rough approach

- Overlap: try longer terms first inside each batch, so the regex engine prefers them.
- Case: options to compare, not decided:
  - a per-entry or per-alias "match any case" flag;
  - case-insensitive for multi-word terms only, case-sensitive for single words;
  - leave as is, and document "add the lower-case form as an alias".

## Open questions

- When "Russelby Manor" matches, should the scene also link Viscount Russelby? The word
  "Russelby" is part of the text, but the writer means the place.
- Do the same names that start a sentence ("Chestnut tree") need special care if the case
  rule changes?
