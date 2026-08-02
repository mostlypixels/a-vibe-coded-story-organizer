# 13 — Landing page

The last hue-named file, and the last `dark:` classes in the codebase.

## Scope

`resources/views/welcome.blade.php` — strip Laravel's stock splash (275 lines) down to the app
name and a themed **Login** button, expressed in role tokens.

## Depends on

12.

## Key decisions already made

- Keep the route and the view. `/` is not redirected away.
- **Login button only.** Registration is disabled — `routes/auth.php` has no `register` route,
  so the page's `@if (Route::has('register'))` block never renders today. Delete it rather than
  carrying dead markup.
- An authenticated visitor gets a Dashboard link instead, as now.
- This removes **all ~20 arbitrary-value hexes** (`bg-[#FDFDFC]`, `text-[#706f6c]`, …) and **all
  35 `dark:` classes** in the codebase. No token can reach an arbitrary value, which is the real
  reason this page never responded to theming.
- The inlined `<style>` block is an `@else` fallback for when no build exists. It is not a
  second stylesheet and is not the problem — but it goes with the splash it styles.
- Do **not** add `@custom-variant dark`. Dark mode is a preset.

## Consult

`expanded/open-questions.md` Q2 and Q6 (one decision, resolved in the grill).

## Tests

- `ExampleTest` still asserts `/` returns 200.
- Add: the page renders a link to `route('login')` for a guest, and to `route('dashboard')` for
  an authenticated user.
- `NoHueNamedColorsTest`'s allow-list is now **empty** — delete the allow-list mechanism itself
  rather than leaving an empty array to be quietly refilled.
- Load `/` under all three presets.
