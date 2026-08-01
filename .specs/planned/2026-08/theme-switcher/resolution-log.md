# Theme Switcher — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- **Contrast ceiling is config, not a column.** It is read only when authoring a preset and by
  the tests guarding those choices — never while rendering. Floors (4.5/3.0) stay global and
  reject; the ceiling is per-preset and warns, because halation is not the only reason to hold
  an opinion about the top of the band and different conditions pull in opposite directions.
- **No `themes` table.** Nothing about a theme varies per row at runtime in this spec. Presets
  live in `config/themes.php`; the only runtime-varying value is `users.theme_slug`. Spec 3
  introduces the table when a row genuinely varies per user.
- **Per-user, not a global singleton.** There are no guests past login except the share page,
  so every themed view has a known user. `users.theme_slug` nullable → `config('themes.default')`;
  the share page and login use the default.
- **Picker lives at `/admin/appearance`.** The Configuration area already renders per-user data
  (`DataTransferController` scopes every list to `$request->user()`), so a per-user preference
  is not out of place there, and the placeholder section already exists.
- **Ramp generation is an artisan command**, not an `app/Support` class — it has no runtime
  caller. Spec 3 promotes it if its live picker needs one.
- **No cache on the rendered style block.** Default store is `database`, so caching would trade
  ~30 `sprintf` calls for a SQL round-trip per page render.
- Three presets, not four. A high-contrast preset was deferred — it is the one whose value
  depends on user feedback that does not exist yet.
- `/` keeps its route but is stripped to a themed login button; registration is already
  disabled. This is also what removes the last `dark:` classes.

## Deviations from the spec/plan

_None yet._

## Issues → resolutions

_None yet._
