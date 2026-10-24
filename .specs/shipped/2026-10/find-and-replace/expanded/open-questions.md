# Open questions

1. **Undo for the whole replace, or per scene?** Recommend per scene for v1. It works now with no schema change. Undo for the whole replace needs a batch ID on `revisions` and a new revert action across entities. This conflicts with the spec's leaning, so decide here.
2. **Entry point: its own page, or inside the search page?** Recommend its own page, with a link from search. The search page lists entities, not matches, and has no write path.
3. **Match across Markdown markup (`miss *Lane*`)?** Recommend no. Show the raw text so the author can see these cases with a second find.
4. **Scene open in the editor during apply?** Recommend no special handling. The editor's next autosave gets the usual conflict from `NoAutosaveConflict`, and the author resolves it there.
5. **Match cap: 2,000?** Recommend 2,000. Above it, the author must narrow the scope.
6. **Fold threshold: scenes start folded above 20 matches?** Recommend yes.
7. **Accents: always accent-sensitive?** Recommend yes. An accent-insensitive replace would change accents the author did not mean to change.
8. **Permission: `update` on the project for the preview too?** Recommend yes. A reader with view-only access has no use for a replace preview.
9. **Act scope: add `actId` to `SearchScope`, or resolve the act to a chapter range in the form?** Recommend adding `actId` to `SearchScope`. The search page then gets act scope too.
