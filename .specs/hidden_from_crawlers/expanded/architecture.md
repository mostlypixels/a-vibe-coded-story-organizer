# Hidden from crawlers — Architecture

Thin controllers, logic in a service, validation in a Form Request, constants in
config — matching the project guidelines. This is the app's **second**
`app/Services` class (after `AttributeTimeline`), justified because building
robots.txt from settings is reusable domain logic with real branching.

## Service: `App\Services\RobotsTxtGenerator`

Single responsibility: turn a `CrawlerSetting` into robots.txt text.

```php
public function generate(CrawlerSetting $setting): string
```

Behaviour:

- **Hidden off** → allow everyone:
  ```
  User-agent: *
  Disallow:
  ```
- **Hidden on** → one allow-group per whitelisted term, then a catch-all block:
  ```
  User-agent: <term>      (repeated per whitelist term)
  Disallow:

  User-agent: *
  Disallow: /             (config('crawlers.disallow_path'))
  ```

Rationale for the shape: a robots.txt-compliant crawler obeys **only the most
specific matching `User-agent` group**. A whitelisted bot matches its named group
(empty `Disallow:` = crawl all); everyone else falls to `*` (`Disallow: /` =
crawl nothing). This is the standard allow-list idiom. Terminate with a trailing
newline. Terms are already line-safe (validated on write).

## Public route + controller: `RobotsTxtController` (invokable)

```php
// routes/web.php — PUBLIC, outside the auth group, next to shared.scenes.show
Route::get('/robots.txt', RobotsTxtController::class)->name('robots.txt');
```

- `__invoke(RobotsTxtGenerator $generator): Response` →
  `response($generator->generate(CrawlerSetting::current()))
      ->header('Content-Type', 'text/plain; charset=UTF-8')`.
- Unauthenticated (crawlers are anonymous). No CSRF concerns (GET).
- **Remove `public/robots.txt`** so the framework route is reached. With
  `php artisan serve` and typical nginx `try_files`, a physical file in `public/`
  shadows the route — deletion is mandatory, and is itself an acceptance check.

## Settings route + controller: `CrawlerSettingController`

Inside the existing `Route::middleware('auth')->group(...)`:

```php
Route::get('/settings/crawlers',  [CrawlerSettingController::class, 'edit'])->name('crawler-settings.edit');
Route::patch('/settings/crawlers',[CrawlerSettingController::class, 'update'])->name('crawler-settings.update');
```

- `edit()` → `view('settings.crawlers.edit', ['setting' => CrawlerSetting::current()])`.
- `update(UpdateCrawlerSettingRequest $request)` →
  `CrawlerSetting::current()->update($request->validated())` then
  `redirect()->route('crawler-settings.edit')->with('status', 'crawler-settings-updated')`.
- Reads like: resolve singleton → (authorize in Form Request) → update → redirect.

### Authorization — the deliberate exception

Every existing resource authorizes by walking up to `Project` via `ProjectPolicy`.
`CrawlerSetting` is **global**, owned by no project or user, so that walk does not
apply. Per the taken decision, authorization is simply "is authenticated":

- Route sits behind `auth` middleware (guests → login redirect).
- `UpdateCrawlerSettingRequest::authorize()` returns `$this->user() !== null`.

This exception must be **called out in `documentation/`** (architecture.md) so a
junior dev doesn't "fix" it by inventing a project walk. See `open-questions.md`
for the alternative (`is_admin`) that was declined.

## Form Request: `App\Http\Requests\UpdateCrawlerSettingRequest`

```php
public function authorize(): bool
{
    return $this->user() !== null; // any authenticated user (global setting)
}

protected function prepareForValidation(): void
{
    // Textarea (one term per line) -> normalised string array.
    $raw   = (string) $this->input('user_agent_whitelist', '');
    $terms = collect(preg_split('/\r\n|\r|\n/', $raw))
        ->map(fn ($t) => trim($t))
        ->filter()
        ->unique()
        ->values()
        ->all();

    $this->merge([
        'enabled'              => $this->boolean('enabled'),
        'user_agent_whitelist' => $terms,
    ]);
}

public function rules(): array
{
    return [
        'enabled'                => ['boolean'],
        'user_agent_whitelist'   => ['array'],
        // Line-safe: no CR/LF, ':' (directive separator) or '#' (comment) so a
        // term cannot forge robots.txt directives.
        'user_agent_whitelist.*' => ['string', 'max:255', 'regex:/^[^\r\n:#]+$/'],
    ];
}
```

Validate-early + centralised rules (guideline). The `regex` is the single guard
that keeps generated robots.txt well-formed; the generator trusts it.

## Meta-robots tag: `x-robots-meta` Blade component

`resources/views/components/robots-meta.blade.php`:

```blade
@props(['force' => false])

@if ($force || \App\Models\CrawlerSetting::current()->isHidden())
    <meta name="robots" content="noindex, nofollow">
@endif
```

Wired into the `<head>` of the public-facing layouts:

- `layouts/app.blade.php`, `layouts/guest.blade.php`, `welcome.blade.php`
  → `<x-robots-meta />` (honours hidden mode).
- `layouts/public.blade.php` (shared scenes) → replace the existing hardcoded
  `<meta name="robots" content="noindex, nofollow">` with
  `<x-robots-meta :force="true" />` — link-only pages stay hidden regardless of
  the global toggle, and the tag now has a single source. Preserve the existing
  explanatory comment.

Content string kept as `noindex, nofollow` to match the current public layout
(spec asks for `noindex`; `noindex, nofollow` satisfies it and is consistent).

## Navigation

Add a "Site settings" link to the existing settings dropdown (desktop, near
`profile.edit`) and the responsive menu, using `x-dropdown-link` /
`x-responsive-nav-link` with `route('crawler-settings.edit')`.

## Documentation to update

- `documentation/architecture.md` — new "Hidden from crawlers" section: singleton
  settings, dynamic robots route, meta component, and the **authorization
  exception** (global setting, not project-scoped).
- `CHANGELOG.md` `[Unreleased] / Added`.
- `CLAUDE.md` — a short paragraph, matching the density of existing feature notes
  (removal of static robots.txt is a gotcha worth recording).
