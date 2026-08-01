# 03 — Audit the CSS entry point

**Depends on:** 01, 02.

## Scope

Verify by hand what the codemod produced at the top of `resources/css/app.css`. Four things,
each with a specific failure the codemod is known to cause.

### `@import`

Exactly `@import "tailwindcss";` replacing the three `@tailwind` directives.

### `@plugin`

```css
@plugin "@tailwindcss/forms";
@plugin "@tailwindcss/typography";
```

Both must be present. Losing one is silent: prose content and form controls simply revert to
unstyled, and no test notices.

### `@source` — the highest-risk item in this task

v4 drops the `content` array and scans automatically, **respecting `.gitignore`**. Of the four
v3 globs, two are covered by auto-detection and two are not:

| v3 glob | v4 |
|---|---|
| `./resources/views/**/*.blade.php` | auto-detected — no directive |
| `./resources/js/**/*.js` | auto-detected — no directive |
| `./vendor/…/Pagination/resources/views/*.blade.php` | **`@source` required** — `vendor/` is gitignored |
| `./storage/framework/views/*.php` | drop — compiled output of `resources/views` |

```css
@source "../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views";
```

> [!WARNING]
> Pagination is live: `resources/views/revisions/index.blade.php` calls `->links()`. Without
> the directive the revision browser's pager renders with its classes in the HTML and no
> matching rules in the stylesheet. No build error, no failing test, and it is a page nobody
> opens by habit.

### `@theme`

- All 55 colour values (`ocean`, `aqua`, `navy`, `sun`, `flame`), same hex, `--color-<name>-<shade>`.
- `--font-sans` with its fallback stack spelled out literally.
- **Plain `@theme`, not `@theme inline`.** Re-check even if 01 already did.

## Not in scope

- The `theme()` call bodies further down the file — task 04.
- Adding `--color-nav-active` — task 06.

## Verification

- `npm run build` succeeds and task 02's guard passes.
- Grep the built CSS for a pagination-specific class and for `.prose` — both must have rules.
  (Task 07 turns this into a permanent test; here it is a manual check.)
- Confirm `--font-sans` in the built `:root` matches what `master` emitted, token for token.

## Consult

`../expanded/architecture.md` — "`@source` — the part auto-detection does not cover" and the
`app.css` header block.
