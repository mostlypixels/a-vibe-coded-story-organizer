# Overview

## User stories

- As the author, I fix "miss Lane" to "Miss Lane" in the whole novel, and I untick "Ollie would miss Lane's company" before I apply.
- As the author, I limit a replace to one chapter while I revise it.
- As the author, I revert one scene if a replace went wrong there.

## Acceptance criteria

- Find is case-sensitive and whole-word by default. Two checkboxes turn each off.
- Find is always accent-sensitive. "Helene" does not match "Hélène", because a replace must not change accents.
- The find text is literal. No regular expressions and no wildcards.
- Scope: whole project, one book, one act, or one chapter.
- The preview groups matches by scene in manuscript order (book, act, chapter, scene position).
- Each match row shows the text around it, with the old text struck through and the new text next to it.
- Each match has a checkbox, ticked by default. "Select all" and "Select none" work for the whole list and for each scene.
- A counter shows "N of M matches selected in K scenes".
- "Select all" covers every match in the scope, including folded scenes.
- Apply changes only the ticked matches.
- Each changed scene gets one manual revision labelled `Replace "<find>" with "<replace>"`.
- If a scene changed after the preview, apply skips the whole scene and lists it.
- After apply, a status message gives the count of matches changed, scenes changed and scenes skipped.
- Word counts and codex references stay correct after apply.
- A non-owner gets 403 on the preview and on apply.

## Non-goals (from spec, plus)

- Preserve-case replace ("lane" → "miss", "Lane" → "Miss").
- Find-only checks with no replace (note #174, part 2).
- Matches that cross Markdown markup (`miss *Lane*`). See open questions.
