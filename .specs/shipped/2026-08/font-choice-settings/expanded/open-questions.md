# Open questions

1. **Does the text scale really move `:root { font-size }`?**
   Recommend yes — Tailwind is rem-throughout, so it scales chrome and prose together,
   which is what a low-vision user is asking for. Cost: fixed-width tables and the sidebar
   grow with it, and measure is out of scope, so long lines get longer. The safe
   alternative is prose-only scaling, which is the half-measure the spec's *Why* rejects.

2. **Does the author accept losing Atkinson as the default?**
   Recommend yes, with no data migration: `null` stays "follow config", the author picks
   Atkinson once. The alternative — backfilling every existing user to `atkinson` — makes
   the config default unreachable forever after and is a migration nobody can undo
   meaningfully. (Pre-V1: the only real data is one seed.)

3. **Live preview, or apply on submit?**
   Recommend apply-on-submit for v1, matching the theme picker, with the per-family
   label rendering and the sample paragraph carrying the preview weight. Live preview
   means Alpine writing `document.documentElement.style` — a second place values reach
   CSS, and the one the security section exists to prevent.

4. **Five bundled families, or fewer?**
   Recommend Inter + Atkinson + Lexend + one serif. Literata and Source Serif 4 are both
   "personal preference serif"; shipping both doubles the italic + variable file set for a
   distinction most users cannot name. Which serif is the author's call.

5. **Italic and bold coverage per family.**
   A manuscript needs italic. Recommend: variable roman + variable italic, latin and
   latin-ext, for every bundled family that has one — and dropping a candidate family that
   does not. Atkinson keeps its current static set (no italic) as a known limitation of
   choosing it.

6. **Does the WYSIWYG editor follow the manuscript font, or stay in the UI font?**
   Recommend follow — writing in one face and reading in another defeats the setting.
   Watch the toolbar: it is chrome inside a manuscript surface and must keep `font-sans`.

7. **`config/fonts.php` or `app/Enums/FontFamily`?**
   Recommend config (see `architecture.md`); `spec.md` says enum. Needs a decision before
   the plan, because validation, the picker and the `@font-face` drift test all key off it.
