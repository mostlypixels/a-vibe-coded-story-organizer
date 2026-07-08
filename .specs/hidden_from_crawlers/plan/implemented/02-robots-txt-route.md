# Task 02 — robots.txt generator & public route

## Scope

- `App\Services\RobotsTxtGenerator::generate(CrawlerSetting $setting): string`:
  - **Hidden off** → `User-agent: *` / empty `Disallow:` (allow all).
  - **Hidden on** → one `User-agent: <term>` + empty `Disallow:` group per
    whitelist term, then `User-agent: *` / `Disallow: <config disallow_path>`.
  - Trailing newline. Trust the line-safety of terms (validated in task 04; the
    seed/test paths must also only use safe terms).
- `App\Http\Controllers\RobotsTxtController` (invokable) →
  `response($generator->generate(CrawlerSetting::current()))
      ->header('Content-Type', 'text/plain; charset=UTF-8')`.
- Route in `routes/web.php`, **outside** the `auth` group (next to
  `shared.scenes.show`): `Route::get('/robots.txt', RobotsTxtController::class)->name('robots.txt')`.
- **Delete `public/robots.txt`** so the route is reached (static file shadows the
  route under `artisan serve` and nginx `try_files`).

## Explicitly NOT in this task

- No meta component (task 03).
- No settings UI to *edit* the values (task 04) — tests here set the model state
  directly via `CrawlerSetting::current()->update([...])`.

## Depends on

Task 01.

## Key decisions already made (binding)

- Product-token allow-groups; whole-site allow/block; no `Crawl-delay`/`Sitemap`.
- Dynamic route, static file removed.

## Consult

`expanded/architecture.md` (generator shape, route placement), `expanded/overview.md`
(acceptance criteria 1–4), `00-overview.md`.

## Tests (extend `CrawlerSettingTest.php`)

- Default/hidden fresh DB → `get(route('robots.txt'))` is 200, `text/plain`, body
  has `User-agent: *` and `Disallow: /`; a row was lazily created.
- Whitelist `['Googlebot']` (hidden on) → `assertSeeInOrder(['User-agent: Googlebot',
  'Disallow:', 'User-agent: *', 'Disallow: /'])`.
- Hidden off → body has `User-agent: *`, `assertDontSee('Disallow: /')`.
- Guest (no `actingAs`) still gets 200.
- `assertFileDoesNotExist(public_path('robots.txt'))`.
