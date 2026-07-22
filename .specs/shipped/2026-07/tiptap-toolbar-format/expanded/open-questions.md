# Open questions

1. **Should both Headings and Table structure become dropdowns, or just one?**
   `spec.md`'s rough approach says "likely" both. Recommendation: **both** — Headings is 4 rarely
   toggled-together buttons (a document mostly uses one or two heading levels at a time), and
   Table structure is 4–6 buttons only meaningful inside a table. Collapsing just one leaves the
   other cluster still bloating the row. See `ui.md` for the concrete dropdown layout.

2. **Should the Table structure dropdown auto-open when the cursor enters a table?**
   Recommendation: **no** — `spec.md`'s non-goals explicitly exclude contextual enable/disable
   ("toolbar state" is a separate, harder concern); auto-opening a dropdown based on cursor
   position is the same category of contextual behavior and should stay out of this refactor.
   Confirm this reading is correct before `plan-tasks` locks it in as a non-goal extension.

3. **Dropdown trigger glyph for Headings when no heading level is active.**
   Recommendation: show a plain `H` (matching the existing per-level `H{level}` label style) when
   `isOn('heading')` is false for every level, and `H1`/`H2`/`H3`/`H4` when one is active — mirrors
   how the current per-level buttons already individually highlight via `isOn('heading', {level})`.
   Confirm this is sufficient, or whether a chevron/caret affix is wanted to visually signal "this
   is a menu, not a direct toggle" (none of the app's existing `<x-dropdown>` usages seem to need
   one for a single-glyph trigger — worth a quick look at `dropdown-link.blade.php`'s call sites
   before deciding either way).

4. **`x-wysiwyg.toolbar-button`'s scope.** `ui.md` recommends the new sub-component cover only the
   plain `cmd()`/`isOn()` toggle shape, leaving Link/Image/Callout as hand-written buttons (they
   call no-arg helper functions, not `cmd()`). Confirm this line is acceptable — the alternative
   (forcing all buttons through one component with a more generic `@click` prop) would remove the
   last few hand-written buttons but makes the component's Blade harder for a junior developer to
   read, trading one kind of duplication for another kind of indirection.
