# Open questions

Each question has a recommended answer.

1. **Which user do the tools act as?** The project owner, as `SceneReviewNoteWriter` does. Record the missing policy check as technical debt.
2. **New `RevisionOrigin` case for agent writes?** No. Use the manual origin with the label "Claude". The label already shows in History.
3. **Automatic backup before writes?** No. Revisions cover field changes, and no tool deletes. Document `BackupDatabase` in `mcp-server.md` for large batches.
4. **A guard for Gardenia Girl?** No. No tool writes prose, and that is the guard for every project.
5. **Book front and back matter (dedication, preface, …) writable?** No. They are the author's prose, like `contents`.
6. **Move and reorder tools?** Not in the first version. A move changes numbering and is easy to undo in the UI only.
7. **Delete tools?** Not in the first version.
8. **Codex media?** Not in the first version.
9. **Event writes on the Start and End events?** Allowed, with the same validation and model hooks as the UI.
10. **Keep the `scene:*` artisan commands?** Yes, for now, on shared services. Remove them in a later change when nothing calls them.
11. **Ship `scripts/db.sh` (read-only SQL in Docker) too?** Yes, as a fallback for questions the tools do not cover. It is small.
12. **One generic `show` / `list` tool, or one per entity?** Generic for reads, to keep the tool definitions small. One per job for writes, for permissions.
13. **Output format?** Markdown text. JSON output only if a later caller needs it.
14. **Large projects?** `project-outline` takes an optional `book`, and `list` caps at 200 rows with a "more" line. Les Misérables (365 scenes) is the size test.
