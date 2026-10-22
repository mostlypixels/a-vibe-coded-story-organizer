# Open questions

1. One refresh icon per section, or one in the panel header for both? **Recommended: one per section.** Each section is its own component. A panel icon needs a cross-component event.
2. Refresh while the right panel is collapsed? **Recommended: yes.** It costs one small request, and the panel is current when it opens. The `<details>` check already skips closed sections.
3. A change during the request drops the response. Retry at once, or wait for the next trigger? **Recommended: wait.** The writer is active in the page, and the next focus or click refreshes.
4. Add a minimum interval between focus refreshes, such as 2 s? **Recommended: no.** One running refresh per section already coalesces the double event. Add a limit only if quick tab switching shows a problem.
5. Should a card whose note or entry was deleted vanish at once? **Recommended: yes**, as a page reload would. Check that `EditorCards` drops a row whose target is gone.
