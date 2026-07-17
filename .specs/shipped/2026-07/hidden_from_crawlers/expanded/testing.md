# Hidden from crawlers — Testing

New file `tests/Feature/CrawlerSettingTest.php` — plain PHPUnit, `RefreshDatabase`,
factories, `actingAs`, `route()` helper (never raw URLs, except `/robots.txt`
which has a named route `robots.txt`). Follow `ProjectTest.php` style.

## robots.txt (public, dynamic)

1. **Default is hidden / blocks all** — fresh DB, no settings row. `get(route('robots.txt'))`
   → 200, `Content-Type` starts `text/plain`, body contains `User-agent: *` and
   `Disallow: /`. Also asserts a `crawler_settings` row was lazily created.
2. **Whitelisted term is allowed** — set hidden on with
   `user_agent_whitelist => ['Googlebot']`. Body contains a `User-agent: Googlebot`
   group followed by empty `Disallow:`, **and** the `User-agent: *` / `Disallow: /`
   block. Use `assertSeeInOrder(['User-agent: Googlebot', 'Disallow:', 'User-agent: *', 'Disallow: /'])`.
3. **Hidden off allows all** — settings `enabled => false`. Body contains
   `User-agent: *` and does **not** contain `Disallow: /` (empty `Disallow:` only).
   Guard the substring trap: assert with `assertDontSee('Disallow: /')`.
4. **Reachable by a guest** — no `actingAs`; still 200 (route is outside auth).
5. **No static file shadows it** — assert `public/robots.txt` does not exist
   (`$this->assertFileDoesNotExist(public_path('robots.txt'))`) so a future
   re-add is caught.

## Meta robots tag

6. **Present when hidden** — `get('/')` (welcome) with default/hidden settings →
   `assertSee('name="robots"', false)` and `assertSee('noindex', false)`.
7. **Absent when not hidden** — settings `enabled => false`; `get('/')` →
   `assertDontSee('name="robots"', false)`.
8. **Shared-scene page is always noindex** — with hidden **off**, the public
   shared-scene layout still emits `noindex` (forced). Build a shareable scene as
   `SharedSceneController`/existing share tests do, hit `shared.scenes.show`, and
   assert `noindex` present. (If share-link setup is heavy, cover the component
   `:force="true"` branch via a thin Blade render test instead.)

## Settings screen (auth)

9. **Guest redirected** — `get(route('crawler-settings.edit'))` without auth →
   `assertRedirect(route('login'))`.
10. **Authenticated user sees the form** — `actingAs($user)->get(...)` → 200,
    sees current toggle + whitelist values.
11. **Update happy path** — `actingAs($user)->patch(route('crawler-settings.update'),
    ['enabled' => '1', 'user_agent_whitelist' => "Googlebot\nBingbot"])` →
    redirect back with status; DB row has `enabled = true` and
    `user_agent_whitelist = ['Googlebot','Bingbot']`. Blank lines/dupes are dropped
    (send `"Googlebot\n\nGooglebot\n"` → `['Googlebot']`).
12. **Turning hidden off** — patch with `enabled` omitted (unchecked box) →
    `enabled = false`; a subsequent `get(route('robots.txt'))` allows all
    (verifies the dynamic route reflects the update in one flow).
13. **Validation: line-unsafe term rejected** — patch with
    `user_agent_whitelist => "Bad: Bot"` (contains `:`) →
    `assertSessionHasErrors('user_agent_whitelist.0')`; DB unchanged.

## Invariants to guard

- `CrawlerSetting::current()` never creates more than one row across repeated
  reads (assert `crawler_settings` count is 1 after several requests).
- No existing invariant is touched: main plotline, bookend events, and position
  ordering are unrelated. Confirm the **full suite** stays green (`composer test`)
  — the meta component runs inside `app`/`guest`/`welcome` layouts, so a broken
  component would ripple into unrelated view assertions.
