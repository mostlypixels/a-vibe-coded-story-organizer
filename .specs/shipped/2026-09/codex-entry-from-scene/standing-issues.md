# Codex entry from the scene editor — standing issues

**What is still true of the shipped code.** Read this before extending the feature.

---

## Accepted costs

### A quick-created entry does not match other scenes until they next save

`SceneCodexEntryController::store()` calls `CodexEntrySaver::create()` with
`rescanProject: false` and resyncs only the scene that prompted the entry. Every other
scene in the project keeps its old `scene_codex_entry` pivot rows until it is next saved
or the project-wide resync command runs, even one whose text already holds the new name.

The alternative is `syncProject()` on every quick entry — a full-project rescan mid-
paragraph, which is the cost this feature exists to avoid.
