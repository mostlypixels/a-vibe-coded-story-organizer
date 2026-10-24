---
status: shipped
shipped: 2026-10-23
planned: 2026-10-23
expanded: 2026-10-23
---

# Find And Replace

## Problem

Small, repeated errors spread across many scenes: wrong title capitals ("duke of Willingshire", "Hyde park", "miss Lane"). It takes too long to fix them by hand in 143 scenes. A blind "replace all" is not safe either, because the same text can have a different meaning: "Ollie would miss Lane's company" must stay as it is. The author must see each match and decide. Source: Gardenia Girl note #174.

## Goals

- Find text in the scene contents of one project and replace it.
- Match the case and match whole words by default. Both can be turned off.
- Limit the search to a scope: the whole project, one book, one act or one chapter.
- Preview before any change:
  - Group matches by scene, in manuscript order (book, act, chapter, scene).
  - Show each match with the sentence around it, and the result after the replace.
  - Each match has a checkbox, ticked by default.
  - Select all and select none, for all matches and for one scene.
  - Show the count of selected matches.
  - Stay usable with many matches (hundreds over a 220K-word novel). "Select all" means every match in the scope, not only the matches on screen.
- Apply changes only the ticked matches.
- Each changed scene saves as a normal manual revision, labelled with the find and replace text, so one revert undoes it.
- After apply, report how many matches changed in how many scenes.

## Non-goals

- Regular expressions.
- Fields other than scene contents (titles, summaries, notes, codex, front and back matter).
- Replace across projects.
- Find-only checks with no replacement (the "acquiesce", asterisk and space checks in note #174). These are a separate feature, but they can use this preview.
- Saved replace rules or a list of rules to run in one go.
- Changes through the MCP. The MCP must never change prose.

## Rough approach

- Follow `ProjectSearch` / `SearchController` to find matches. Write changes through `SceneSaver` and `RevisionRecorder` with a label, as other manual checkpoints do.
- Match on the text, not the HTML: a match must never cut a tag or change markup.
- A match ID is the scene plus the match position. Apply checks each scene again. If the scene changed after the preview, skip that scene and report it. Do not guess.

## Open questions

- Where is the entry point: the search page, or a separate page?
- One revision for each scene, or one group revision for the whole replace, so one undo reverts all scenes? Leaning: one undo for the whole replace, as VS Code does.
- Add a "replace in this scene" action next to each scene? Leaning: yes, as VS Code does for each file.
- Can a match go across inline formatting (for example, `miss <em>Lane</em>`)?
- What happens to a scene that is open in the editor with unsaved changes during apply?
- How does a long list stay easy to use: fold scenes, pages, or both? Is there a cap on the number of matches? Leaning: each scene folds and shows its match count; scenes fold by default when the list is long; a cap, as VS Code has.
- Prior art: VS Code search lists matches by file, with old text struck through next to new text. Google Docs steps through one match at a time, which is too slow for hundreds. Scrivener replaces blind, with no undo. Keep checkboxes, not VS Code's ✕, so a match can be ticked again.
