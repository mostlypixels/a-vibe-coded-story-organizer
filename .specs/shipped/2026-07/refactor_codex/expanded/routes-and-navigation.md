# Routes & navigation — finding 9

**Where:** `routes/web.php:64-72` (three identical
`->whereIn('type', ['characters', 'locations', 'organizations'])`) and
`resources/views/layouts/navigation.blade.php:64-73` (desktop dropdown) + `:195-204`
(responsive menu) — five literal string lists for a concept `CodexEntryType` already models
(`routeKey()`, `pluralLabel()`, `fromRouteKey()` all exist).

Adding a fourth type (the codex spec's open questions mention "Items") currently requires
editing five scattered lists or the new type silently 404s / never appears in the nav. This
violates the "no magic strings" guideline the enum was created to satisfy.

## Fix 1 — enum helper

Add to `app/Enums/CodexEntryType.php`:

```php
/**
 * Every type's route key, for route constraints and nav iteration.
 *
 * @return array<int, string>
 */
public static function routeKeys(): array
{
    return array_map(fn (self $type) => $type->routeKey(), self::cases());
}
```

While in the file, `fromRouteKey()` can be rewritten to iterate `self::cases()` matching on
`routeKey()` instead of its own literal `match` — one list fewer to keep in sync. Optional but
in the spirit of the finding.

## Fix 2 — one shared route constraint

In `routes/web.php`, replace the three `->whereIn(...)` calls with a single group-level
pattern. Two options:

- **Grouped `whereIn` (recommended)** — keeps the constraint local to the codex block and
  visible where the routes are defined:

  ```php
  Route::whereIn('type', CodexEntryType::routeKeys())->group(function () {
      Route::get('/projects/{project}/codex/{type}', [CodexEntryController::class, 'index'])
          ->name('projects.codex.index');
      Route::get('/projects/{project}/codex/{type}/create', ...)->name('projects.codex.create');
      Route::post('/projects/{project}/codex/{type}', ...)->name('projects.codex.store');
  });
  ```

- `Route::pattern('type', implode('|', CodexEntryType::routeKeys()))` in
  `app/Providers/AppServiceProvider::boot()` — global, but moves the constraint away from the
  routes and would bind `{type}` app-wide (a future non-codex `{type}` param would inherit
  it). Prefer the grouped form.

Behavior must stay identical: unknown `{type}` 404s **before** the controller (keep the
existing comment at `routes/web.php:61-63`).

## Fix 3 — iterate the enum in navigation

In `resources/views/layouts/navigation.blade.php`, replace the three hardcoded
`<x-dropdown-link>` entries (desktop, `:64-73`) and the three `<x-responsive-nav-link>`
entries (`:195-204`) with loops:

```blade
@foreach (\App\Enums\CodexEntryType::cases() as $codexType)
    <x-dropdown-link :href="route('projects.codex.index', [$project, $codexType->routeKey()])">
        {{ __($codexType->pluralLabel()) }}
    </x-dropdown-link>
@endforeach
```

Responsive variant likewise; note the responsive block puts the `:active` matcher
(`request()->routeIs('projects.codex.*') || request()->routeIs('codex.*')`) only on the
**first** link (`:195`) — in the loop, apply it via `$loop->first` to preserve the current
behavior exactly:

```blade
:active="$loop->first && (request()->routeIs('projects.codex.*') || request()->routeIs('codex.*'))"
```

(Or decide the active state belongs on every codex link — a behavior change; if taken, note it
in the CHANGELOG entry. See `open-questions.md` Q4.)

The `@foreach` + enum-in-Blade pattern is presentation-only iteration over reference data —
consistent with guidelines (no queries, no logic; the enum is `app/Enums` reference data like
`PlotlineColors`).

## Docs

CLAUDE.md's Codex paragraph documents the `->whereIn('type', …)` constraint — update the
wording to reflect the enum-derived constraint once implemented.
