# Breadcrumbs — Open questions

- **Section crumbs (Story/Codex/Timeline/Tools): plain text or link?**
  Recommend **plain text** — they are dropdown triggers with no page, and the spec's example
  shows Codex unlinked. (Alternative: link Codex→first type, Story→overview; rejected —
  invents a target the menu doesn't have.)

- **Fully-central builder vs. per-page leaf label?**
  Recommend **fully central**: `Breadcrumbs` reads the route-bound model for the leaf, so
  no view passes anything (0 pages touched for labels; ~30 only lose their header slot). The
  alternative — each edit/create page passes `$breadcrumbLeaf` — is more explicit but spreads
  the trail across 20 files and can drift. Go central unless a leaf label needs data not on
  the bound model.

- **Should `/admin/*` get breadcrumbs too?**
  Recommend **no, this spec** — admin has its own `AdminNavigation` and sidebar; a separate
  admin-breadcrumb pass can follow. Keep the fallback-to-`header`-slot for admin. (Automatic:
  admin routes have no project binding, so the builder already yields nothing there.)

- **Revisions history/compare leaf label + trail tail?**
  These pages (`revisions.index/compare/field/field-compare`) can't resolve a project from a
  route param, so the view passes the tail explicitly (architecture). Open: exact leaf wording.
  Recommend `Revisions` (linked) › `<Entity> "<title>" — History` / `… — Compare` /
  `… — <field> history`. Confirm the entity gets named (helps orientation) vs. a bare
  `History` leaf. Trivial to adjust once the browser UI wording is settled.

- **Dashboard root: show a one-item `Dashboard` trail, or no band?**
  Recommend **show it** (`Dashboard`, current) for a consistent band height and a visible
  "you are here". Cheap; alternative is a jumpy layout as you move on/off the root.

- **Leaf label for edit/create pages: bare name or action-precise?**
  **Decided: action-precise.** The leaf names the action, not just the thing —
  `Edit character — Mélusine`, `New Scene`, `Edit Event — <title>`. Matches the spec's
  example and disambiguates edit vs. create at a glance.

- **Visible `<h1>` after removing the header title.**
  Some pages showed their only heading in the header band. Confirm each converted page keeps
  a body heading (ui.md) — is a per-page audit acceptable, or should the band keep a visually
  small `<h1>` from the leaf label for heading semantics? Recommend **per-page body heading**
  (most already have one).
