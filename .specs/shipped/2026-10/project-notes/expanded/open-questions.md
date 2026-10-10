# Open questions

Each line: question → recommended answer.

1. Body format: rich HTML or Markdown? → **Rich HTML**, like every other description. Paste covers the `.ai` Markdown.
2. Manifest version: dropping scene `notes_file` is a "removed field". Bump to 6? → **Yes**, support 4, 5, 6; 4 and 5 map scene `notes_file` to a note.
3. Category depth: enforce 3 on the server too, or only in the UI? → **Server too**, one rule, so imports and tests agree.
4. Category delete: move contents up, or block when not empty? → **Move up** (spec rule). Confirm.
5. Category select on a note shows only direct notes, or include sub-categories? → **Direct only**, like folders.
6. Migrated scene notes: no category, or a "Scene notes" category? → **No category**. The link to the scene already says where they belong.
7. Migrated title: `Notes: <scene name>`? → **Yes**.
8. Duplicate a scene: copy its note links? → **Yes**, like other references in `SceneDuplicator`.
9. Table of contents on the edit page too? → **No**, read view only for now. Tiptap has its own scroll.
10. "Plot threads" starter category: keep it, given the planned plotline status? → **Keep** for now. It is only a name the writer can delete.
11. Starter categories for demo projects (`InstallsDemoProjects`)? → **Yes**, so the demo shows them.
12. Can a note link to the fixed Start and End events? → **Yes**. Nothing breaks, and blocking it needs an extra rule.
13. Notes card placement: read and edit pages of all seven types, or only edit? → **Both**. The read views exist so writers don't open forms to look.
14. Move notes between projects? → **No**. `move-between-projects` is shelved; notes follow it if it returns.
