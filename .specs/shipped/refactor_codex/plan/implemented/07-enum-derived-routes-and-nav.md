# 07 — Enum-derived routes & navigation (finding 9)

## Scope

Remove every literal `characters|locations|organizations` list; derive from
`CodexEntryType`.

- `app/Enums/CodexEntryType.php`: add `public static function routeKeys(): array`
  (`array_map(fn (self $type) => $type->routeKey(), self::cases())`). Optionally rewrite
  `fromRouteKey()` to iterate `self::cases()` matching `routeKey()` — one list fewer.
- `routes/web.php:64-72`: replace the three `->whereIn('type', [...])` calls with one
  grouped constraint:
  `Route::whereIn('type', CodexEntryType::routeKeys())->group(...)` around the three
  nested codex routes. **Not** a global `Route::pattern` in a provider (binding decision —
  keeps the constraint local, avoids binding `{type}` app-wide). Unknown `{type}` must
  still 404 before the controller; keep the explanatory comment.
- `resources/views/layouts/navigation.blade.php`: replace the three hardcoded
  `<x-dropdown-link>` entries (`:64-73`) and three `<x-responsive-nav-link>` entries
  (`:195-204`) with `@foreach (\App\Enums\CodexEntryType::cases() as $codexType)` loops
  using `routeKey()` / `pluralLabel()`.
- **Nav active-state fix (Q4, binding):** the responsive nav currently marks only the
  first (Characters) link active on any codex page. In the loop, highlight the *current*
  type instead — compare `request()->route('type')` to `$codexType->routeKey()` (also
  handle the flat `codex.*` edit pages, where the type comes from
  `request()->route('codexEntry')?->type`). Behavior change → CHANGELOG `Changed` (task 09).

Does **not** add a fourth entry type or touch the codex controllers.

## Depends on

Nothing (any time after 01 numerically; listed after the correctness tasks).

## Key decisions already made

- Grouped `whereIn`, not `Route::pattern`.
- Q4 resolved: fix the active-state quirk now, note it in the CHANGELOG.
- Enum iteration in Blade is presentation-only reference data — allowed by guidelines.

## Consult

`.specs/refactor_codex/expanded/routes-and-navigation.md`, `open-questions.md` Q4.

## Tests

- Existing `CodexEntryTest` coverage (each type's index resolves; unknown type 404s) is the
  regression net — must stay green through the constraint refactor.
- `CodexEntryType::routeKeys()` returns exactly
  `['characters', 'locations', 'organizations']` (pins the shared contract).
- `test_navigation_lists_every_codex_type`: authenticated GET of a project-scoped page →
  `assertSee` each `pluralLabel()` (guards the nav loops rendering all cases).
