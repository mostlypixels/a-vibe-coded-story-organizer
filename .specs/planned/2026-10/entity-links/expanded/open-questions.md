# Entity links — open questions

1. **`entity:` scheme in `href` instead of the spec's `data-link`?** Recommend yes: Markdown scene text cannot carry attributes.
2. **Note-to-note links: in the text only, or also as structured pivot links?** Recommend text only for now; hide `note` in the pivot picker.
3. **Convert the existing research notes (note 200 and its siblings)?** Recommend a one-off scratchpad script: match `outline/NN-<chapter>/M-<scene>.md` to a scene by chapter and scene name, drop `:52` line numbers, match `<slug>.md` to a note by title, and leave text that has no match.
4. **Turn the plain-text path citations into links, or only real `<a>` tags?** Recommend both. In note 200 the paths are plain text in table cells, not anchors.
5. **Should `x-rich-text` require `project`, or should it read the project from the model?** Recommend a required prop: explicit, and a missing prop fails loudly.
6. **Backlinks card on every target type in V1, or only scene, codex and note?** Recommend all types. The card is one component, and `LinkTargetType` lists the types.
7. **Mark dead links in the editor?** Recommend no for V1. The read view marks them.
8. **Static site: link to the target page when it exists (a scene inside the book), and use plain text otherwise?** Recommend plain text for V1, as the draft said.
