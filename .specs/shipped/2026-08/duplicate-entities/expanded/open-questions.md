# Duplicate entities — resolved questions

Settled in the grill on 2026-08-10. Binding: do not re-litigate while implementing.

| # | Question | Resolution |
|---|---|---|
| 1 | Which entities? | **Scene and CodexEntry only.** Acts and chapters hold no prose of their own — a copy would be an empty shell. Events, plotlines, codex attribute definitions and projects are out. |
| 2 | What does "duplicate children" mean? | Rows the entity **owns** (a codex entry's aliases, media, attribute values). It never means the manuscript tree: no act copies its chapters, no chapter copies its scenes. The design stays open to a future entity-owned table joining the list. |
| 3 | Name uniqueness scope | Project-wide per type: every scene in the project; every codex entry of the **same `CodexEntryType`** in the project. Case-insensitive. |
| 4 | Reject a submitted name that collides? | **No.** No schema or Form Request enforces name uniqueness anywhere in the app; Duplicate must not be the one screen that does. |
| 5 | The naming step's shape | A modal dialog on the current page, one prefilled input, posting to the duplicate route. Follows `x-delete-with-move-dialog`. |
| 6 | Where does the writer land? | The copy's edit page, from both entry points, with a "Duplicated." badge. |
| 7 | File copy vs. transaction order | Copy files to fresh paths **first**, insert rows in a transaction, delete the copies in a `catch`. Leaks orphan files in the worst case — never a row pointing at a missing file. |
| 8 | Copy the codex aliases? | **Yes**, verbatim. The copy then matches the same scenes as the original until the writer edits them; that is visible and self-correcting, and retyping aliases is exactly what this feature removes. |
| 9 | Copy revision history? | No. A copy is new work and gets its baseline on first autosave. |
| 10 | Copy the scene's `status`? | Yes, verbatim. Silently downgrading loses a value the writer set deliberately. |
| 11 | One duplicator service or two? | **Two** — `SceneDuplicator`, `CodexEntryDuplicator`. They share only the name helper, so one class holding both would be a namespace, not a service. |
| 12 | Bulk duplication? | Out of scope. No index in the app has multi-select. |
