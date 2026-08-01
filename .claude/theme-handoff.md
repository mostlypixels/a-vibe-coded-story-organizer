# Theme handoff

Tailwind 4 port is done and shipped to `.specs/shipped/2026-08/tailwind-4/`. Branch
`tailwind-4`, not yet merged, not yet PR'd.

**Next: a refactor and cleanup pass over the theme.** The port deliberately changed nothing it
did not have to — colour names, hex values and class usage all still mirror v3, and the codemod
diff was accepted wholesale. So the tree now carries v3 shapes expressed in v4 syntax. Look for:

- Utilities the codemod translated literally where a v4 idiom is clearer.
- The `@theme` block itself: 55 colour values, still `ocean`/`aqua`/`navy`/`sun`/`flame`.
  Renaming to role tokens is `theme-switcher` (spec 2), so **check the boundary before moving
  anything** — cleanup here should not pre-empt that spec's decisions.
- Duplicated class strings across Blade components that a component or variant would collapse.
- Whether the border-colour shim's 88 width-only `border` usages are worth fixing at the source
  now rather than waiting for spec 2 to drop the shim.

**Read `resolution-log.md` first.** Load-bearing findings that will otherwise be rediscovered:

- **v4 scans Markdown.** A class named in prose becomes a real rule; `.specs/` and
  `documentation/` are excluded via `@source not` in `app.css`. Any new docs folder needs the
  same, or the build ships phantom utilities.
- **`--radius-full` does not exist.** `rounded-full` is hard-coded `calc(infinity * 1px)`;
  spec 2 cannot make it themeable without inventing a variable.
- **The codemod ran twice** during this port and shifted 93 utilities one step down the v4
  scale (`shadow-sm`→`shadow-2xs`, `rounded`→`rounded-xs`). Caught by the browser pass, fixed.
  If shadows or radii look off anywhere, suspect this before suspecting the theme.
- **A green suite proves nothing about CSS.** `composer test` renders none of it and
  `npm run build` succeeds on silently-dropped declarations. The guards that do bite:
  `css-build.test.js` (dangling `var()`) and `css-source-smoke.test.js` (`@source` reach).
- **`standing-issues.md`** lists the accepted visual differences from v3 — don't "fix" them.

**Verify in a browser, against the build.** `public/hot` must be absent and the dev containers
stopped; they have restarted themselves mid-run and re-created it. The browser pass compared
`master` from a parallel `git worktree` on :8001 — reuse that method for anything visual.
