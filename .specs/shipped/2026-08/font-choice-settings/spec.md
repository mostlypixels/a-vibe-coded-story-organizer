---
status: shipped
shipped: 2026-08-11
planned: 2026-08-11
expanded: 2026-08-10
---

# Font Choice Settings

A per-user choice of typeface and text sizing: font family, base size, and line height.

Authenticated users only.

## Why

A writing app is looked at for hours. Dyslexia, astigmatism, convergence insufficiency and
plain eye strain each pull the ideal text in a different direction, and only the person
affected can judge the result. Offering size alone is the common half-measure — line
height matters as much.

## Scope

* **Font family**, from a curated list. Separate choices for **UI chrome** and
  **manuscript body** — a writer may want a proportional UI and a serif draft.
* **Base size and line height**, each within a fixed range.
* All values are custom properties on `:root`, chrome and manuscript alike — one override
  block, the same mechanism the theme tokens use. The prose surfaces (`x-rich-text`,
  `x-wysiwyg`, and the app's `.prose` rule in `resources/css/app.css`) consume the
  manuscript ones; they never define them.
* Stored per user, authenticated only. Public pages get the defaults — Inter and the
  default sizing. A guest bothered by a shared scene's display has reader mode in Edge
  and Firefox.

> [!WARNING]
> **A font is a file, not a value.** An arbitrary Google Font means a runtime request to a
> third party — which the project's crawler/privacy posture rules out — or pre-bundling.
> Scope is a curated list bundled through Vite.

Candidate list:

* **Inter** — new default. Sans-serif, familiar, assumes no visual impairment.
* **Atkinson Hyperlegible** — designed for low vision; self-hosted since `1d3cc17`.
* **Lexend** — for dyslexia.
* **Literata** — serif, reading-focused; personal preference.
* **Source Serif 4** — serif; personal preference.

Plus families that need no download: Arial, Verdana, Georgia, and the system UI stack.

> [!NOTE]
> **The default changes.** Atkinson is currently the only family the app names
> (`resources/css/app.css` → `--font-sans`), chosen for the author's astigmatism. It stays
> in the list and one setting away; Inter takes the default because most users have never
> met Atkinson. Decide what existing users see — the new default, or their implicit
> Atkinson kept.

## Security

Values land inside a `<style>` block — a CSS injection surface.

* Families validated against the curated list, no-download ones included — one
  `app/Enums/FontFamily` is the single source for both `Rule::enum(...)` and the bundling.
* Sizes validated as numbers in a fixed range; units come from the server, never from input.
* Never render an unvalidated value into the style block.

## Out of scope

* Colors and theming.
* Text alignment — left-aligned everywhere, not a setting.
* Uploading a font file: we will never allow it.
* Measure / line length. A future interface-wide width container will own that; this spec
  leaves `x-rich-text`'s `max-w-none` alone.
* Per-project or per-document typography.

## Open questions

* Live preview as the sliders move, or an apply step?
* Bundle weight: variable fonts or static weights, and which subsets — five families is
  the list, not the budget.

## Done when

* UI font, manuscript font, size and line height persist and apply.
* A logged-out visitor on a public page gets the defaults, unaffected by any user's
  settings.
* A family outside the list or a size outside the range is rejected, with a test proving
  nothing reaches the style block.
* Keyboard-accessible throughout — a display settings page failing keyboard access is a
  contradiction.
