# 01 — Codemod to a green build

**Depends on:** nothing.

## Scope

Get the project building on Tailwind 4. Deliberately the largest and least surgical task in
the plan — the codemod's diff is accepted wholesale here, and tasks 03–04 audit it.

1. `npx @tailwindcss/upgrade` at the repo root. Let it do the directive swap, the config
   extraction, the utility renames and whatever `theme()` rewrites it manages.
2. Reconcile `package.json` to the intended end state:
   - remove `autoprefixer`, `postcss`, `tailwindcss@^3.1.0`
   - add `tailwindcss@^4`
   - keep `@tailwindcss/vite@^4.0.0` (already present, previously unused)
   - keep `@tailwindcss/forms@^0.5.2` and `@tailwindcss/typography@^0.5.20` untouched
3. Wire the plugin into `vite.config.js` — `tailwindcss()` **after** `laravel()`. Leave the
   `server` block alone; task 08 owns it.
4. Delete `postcss.config.js` and `tailwind.config.js`.
5. `npm install`; confirm the duplicate nested Tailwind under
   `node_modules/@tailwindcss/vite/node_modules/` is gone.
6. `npm run build` until it succeeds.

## Not in scope

- Judging whether the codemod's output is *correct* — tasks 03 and 04.
- The border shim (05), the nav token (06), any test (02, 07), Docker (08), docs (10).
- Fixing anything that merely looks wrong. Record it and move on; task 09 owns appearance.

## Key decisions

- **Delete both config files.** No `@config "../../tailwind.config.js"` escape hatch — see
  `00-overview.md` §2.
- **`@theme`, never `@theme inline`.** If the codemod emits `inline`, change it. This is the
  most expensive available mistake and it surfaces one spec later.
- Accept a large diff. Do not hand-tidy the codemod's output in this task; that hides which
  changes were the tool's and which were yours, and 03–04 need that distinction.

## Watch for

- The codemod may leave `@config` pointing at the old config instead of extracting `@theme`.
  If so, extract by hand — 55 colour values plus the font stack.
- `--font-sans` must spell out its fallback stack literally. The v3 config spread
  `defaultTheme.fontFamily.sans`, and there is no JS import in CSS. **Copy the exact list out
  of `node_modules/tailwindcss/…/defaultTheme` before `npm install` removes the v3 package** —
  once it is gone, the list has to be reconstructed from memory or documentation.
- The `@font-face` blocks at the top of `app.css` (self-hosted Figtree and Atkinson
  Hyperlegible Next, `public/fonts`, air-gapped by design) are plain CSS. Leave them.

## Tests

None new. Run `composer test`, `npm run test` and `composer lint` to confirm nothing PHP-side
moved — the PHP diff for this task must be empty.

> [!WARNING]
> A green suite here means only "PHP untouched". It says nothing about the stylesheet. Do not
> report this task as verified beyond "the build succeeds".

## Consult

`../expanded/architecture.md` — build pipeline, `package.json` table, the `@theme` section.
