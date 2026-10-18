# Open questions

1. Spec example says the codex entry loads "into the card in the notes panel". Typo for the codex panel? — Recommend: yes, each section holds its own cards.
2. "Codex entries and attributes": search attributes too, or show attributes on entry cards? — Recommend: show on cards only, resolved as of the scene's event.
3. Codex search matches names only (`LinkTargetType::candidates()`). Match aliases too? — Recommend: yes, add alias matching to the codex candidates; the Link to… dialog gains it too.
4. Scene switch: full page load, with open cards kept in `sessionStorage`? — Recommend: yes; swapping only the editor risks lost saves for little speed.
5. Edit title, status and description in the editor too? — Recommend: no; the title shows with a link to the full edit page.
6. Recent scenes: whole project or the active book? — Recommend: whole project, like the dashboard, with the book in the context line.
7. Recent list length? — Recommend: 15.
8. Phone and tablet width: panels as overlay drawers, collapsed by default, stored widths ignored? — Recommend: yes.
9. Auto-open cards for the notes linked to the scene, or codex entries it mentions (`ReferencingScenes`)? — Recommend: not in v1; add a "Mentioned in this scene" list later.
10. Cards editable inline (note body autosave)? — Recommend: no, read-only with a link; keeps one autosave field per page.
11. Should "Write" replace the logo's Dashboard target? — Recommend: no; the logo stays Dashboard.
