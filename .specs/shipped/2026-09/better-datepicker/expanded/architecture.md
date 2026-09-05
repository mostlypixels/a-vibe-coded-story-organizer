# Architecture

## `LocaleChoice` resolver — `app/Support/LocaleChoice.php`

Value object over one `config('locales.supported')` entry. Mirrors `ThemePreset` exactly.

```php
final readonly class LocaleChoice {
    public function __construct(
        public string $slug,   // 'fr'
        public string $name,   // 'Français'
        public string $carbon, // 'fr'
        public int $clock,     // 12 | 24
        public string $order,  // 'mdy' | 'dmy' | 'ymd'
    ) {}
    public static function resolve(?string $slug): self; // null / stale -> default
    public static function all(): array;                 // picker options, keyed by slug
}
```

## `DateFormat` — `app/Support/DateFormat.php`

The single home for turning a `Carbon` + a `LocaleChoice` into a display string. Nothing else
calls `->format()` on an event date after this feature.

- `date(CarbonInterface, LocaleChoice): string` — month name + day + year in locale order.
- `dateTime(CarbonInterface, LocaleChoice): string` — the above plus time in the locale clock.
- Builds an `isoFormat` token string from `order` + `clock`, applies `->locale($choice->carbon)`.
  No timezone conversion (event dates are in-universe).

## The current user's locale

- Resolve once per request from `auth()->user()?->locale`. A `View::share` in a middleware or
  `AppServiceProvider` exposes the resolved `LocaleChoice` (and the `DateFormat` helper via a
  Blade component, see `ui.md`) so views never re-resolve.

## Preference plumbing — reuse, don't add

- **Column write:** extend `UpdateAppearanceRequest::rules()` with
  `'locale' => ['nullable', Rule::in(array_keys(config('locales.supported')))]`. No controller
  change — `AppearanceController::update` already saves `$request->validated()` wholesale.
- **Picker options:** `AppearanceController::edit` passes `LocaleChoice::all()` and the active
  slug, exactly as it does for themes/fonts.
- **No policy:** appearance writes to the acting user only; same as the rest of that form.

## The picker component

`<x-date-field>` (see `ui.md` for markup). Server-facing contract unchanged:

- Props: `name`, `value` (`Y-m-d\TH:i` or empty), `min`, `max`, `required`.
- Emits a single hidden `<input name="{name}">` carrying `Y-m-d\TH:i`; Alpine recomposes it on
  every segment change. Controllers, `UpdateCodexEntryRequest`, `WithinEventWindow`, and
  `EventController::datetimeBounds` are untouched.
- Reads the shared `LocaleChoice` for segment order, month labels, and clock.

## Display-site rollout

Replace inline `->format(...)` on event dates with the shared component/`DateFormat`. Known
sites: `events/index.blade.php`, `codex/partials/as-of.blade.php`,
`codex/partials/attribute-timeline.blade.php`, the event-picker option lists, and the
`single-event-field` option labels. Grep `event_datetime->format` to find the rest.
