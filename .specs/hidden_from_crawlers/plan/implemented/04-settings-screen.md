# Task 04 — Settings screen

## Scope

The authenticated in-app configuration surface.

- Routes (inside the existing `Route::middleware('auth')->group(...)`):
  - `GET  /settings/crawlers` → `CrawlerSettingController@edit`, name `crawler-settings.edit`.
  - `PATCH /settings/crawlers` → `CrawlerSettingController@update`, name `crawler-settings.update`.
- `App\Http\Controllers\CrawlerSettingController`:
  - `edit()` → `view('settings.crawlers.edit', ['setting' => CrawlerSetting::current()])`.
  - `update(UpdateCrawlerSettingRequest $r)` → `CrawlerSetting::current()->update($r->validated())`
    then redirect to `crawler-settings.edit` with `status = 'crawler-settings-updated'`.
- `App\Http\Requests\UpdateCrawlerSettingRequest`:
  - `authorize()` → `$this->user() !== null` (global setting; any authenticated user).
  - `prepareForValidation()` — split the `user_agent_whitelist` textarea by
    `/\r\n|\r|\n/`, trim, filter blanks, `unique`, reindex; `merge(['enabled' =>
    $this->boolean('enabled'), 'user_agent_whitelist' => $terms])`.
  - `rules()` — `enabled: boolean`; `user_agent_whitelist: array`;
    `user_agent_whitelist.*: ['string','max:255','regex:/^[^\r\n:#]+$/']`.
- `resources/views/settings/crawlers/edit.blade.php` — `<x-app-layout>` shell like
  `profile/edit`; checkbox `enabled`, textarea `user_agent_whitelist` (value handles
  both array-from-`old()` and array-from-DB, see `expanded/ui.md`), `x-input-error`
  with the `user_agent_whitelist.*` glob, `x-primary-button` "Save", "Saved." toast,
  and a "Preview robots.txt" link (`route('robots.txt')`, `target="_blank" rel="noopener"`).
- Nav links: `x-dropdown-link` (desktop settings dropdown, near `profile.edit`) and
  `x-responsive-nav-link` (responsive menu), both `route('crawler-settings.edit')`,
  label "Site settings", unconditional.

## Explicitly NOT in this task

- No new role/`is_admin`. No per-entry whitelist table.
- Does not re-touch the generator/route (task 02) or the meta component (task 03),
  but its end-to-end test hits the real `/robots.txt` from task 02.

## Depends on

Task 01, Task 02 (end-to-end test fetches `/robots.txt`).

## Key decisions already made (binding)

- Any authenticated user; `authorize()` = `$this->user() !== null`.
- JSON column + textarea; line-safe regex is the single guard the generator trusts.
- Checkbox omitted (unchecked) ⇒ `enabled = false` via `$this->boolean('enabled')`.

## Consult

`expanded/architecture.md` (Form Request, controller), `expanded/ui.md` (view + nav +
textarea value handling), `expanded/testing.md`, `00-overview.md`.

## Tests (extend `CrawlerSettingTest.php`)

- Guest → `get(route('crawler-settings.edit'))` `assertRedirect(route('login'))`.
- Authenticated `get` → 200, shows current toggle + whitelist.
- Update happy path: `patch` with `enabled=1`, whitelist `"Googlebot\nBingbot"` →
  redirect + row has `enabled=true`, `['Googlebot','Bingbot']`; blanks/dupes dropped
  (`"Googlebot\n\nGooglebot\n"` → `['Googlebot']`).
- Unchecked box (`enabled` omitted) → `enabled=false`; then `get(route('robots.txt'))`
  allows all (dynamic route reflects the update end-to-end).
- Line-unsafe term (`"Bad: Bot"`) → `assertSessionHasErrors('user_agent_whitelist.0')`,
  row unchanged.
