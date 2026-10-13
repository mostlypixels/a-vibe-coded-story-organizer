# Container Width — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

An exception log: a task that went to plan gets no entry, because the diff and the task
file already record what was built. Bullets under the headings below, root cause first.

## Feedback & decisions

- 2026-10-11 grill: default 70 %; lowest step 50 %; steps apply from `md`; one control (manuscript follows page); names `page_width` / "Page width".
- 2026-10-11: labels via a `ScaleLabel` `width` format instead of `PageWidth::label()`, so page and switcher share one path.

## Deviations from the spec/plan

- Added `PageWidth::options()` (step => CSS value). The Appearance page, the preview map and the switcher (03) all need the same map, so it lives in one place.
- `ScaleLabelTest` now extends `Tests\TestCase`: the `width` format calls `__()`, which needs the app.

## Issues → resolutions

- `--page-width` had no declaration in the built CSS: it was set only inline in `theme-style`, so `css-build.test.js` (dangling `var()`) failed once `npm run build` ran. Fixed with a `70%` default in `resources/css/app.css`, like the manuscript tokens. Missed because the PHP suite does not build CSS.
- `AppearancePreviewMap::build()` return type had string keys only; `page_width` has integer keys. Widened to `array<int|string, mixed>`.
- 2026-10-11, task 02: two filtered `artisan test` runs hung at 100 % CPU for 8–20 min, with no child process. The cause is not confirmed: a failing assertion printed a huge minified JS bundle. Stopped by hand; the next run passed. Watch for long runs.
- `--page-width` default 70 % now lives in `config/page_width.php` and `resources/css/app.css`. Change both together.
- 2026-10-13, task 03: `assets-state.sh` failed on the host (`public/hot`, pending host migration) while the Docker stack served the app. The container's Vite dev server answered nothing on port 5173 (full accept queue), so every page hung in the browser. Fixed for the check by removing `public/hot`; pages then used the fresh build. The Docker database already had the migration. Cause of the wedged Vite is not confirmed; `make up` restarts it.
