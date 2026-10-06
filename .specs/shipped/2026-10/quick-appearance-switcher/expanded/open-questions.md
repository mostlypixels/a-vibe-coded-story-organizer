# Open questions

All answered in the planning grill (2026-10-06).

1. **Switcher on the Appearance page itself?** → Hidden on `admin.appearance.edit`.
2. **Step size only, or also line spacing?** → Both. Each font gets a size row and a spacing row.
3. **Show at mobile width?** → Yes, beside the hamburger.
4. **Font names in their own face in the `<select>`?** → Yes, best effort with
   `style="font-family"` on each option.
5. **Mark accessible families in the select?** → No.
6. **Reset to defaults in the panel?** → No.
7. **Route name?** → `admin.appearance.switcher`.
8. **Debounce rapid `+` clicks?** → No timer. The per-field queue coalesces.
9. **Preload the panel?** → No. Load on first open.
