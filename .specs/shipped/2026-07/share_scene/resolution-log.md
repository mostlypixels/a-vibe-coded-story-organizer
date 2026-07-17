# Share scene — resolution log

The running record of feedback/decisions, deviations from the spec/plan, and
issues → resolutions found while implementing and verifying this feature. The
`plan-implementer` agent appends here per task; `ship-plan` consolidates it. Read it
before extending the feature.

## Feedback & decisions

- **Duration:** owner picks per share from a config whitelist (24h / 7d / 30d) — not a single
  global default. Validated with `Rule::in`.
- **Expired/unknown link:** friendly branded expired page (410) for an expired token; 404 for an
  unknown token.
- **Public layout:** dedicated `layouts/public.blade.php` (`<x-public-layout>`), not a reuse of
  the Breeze guest layout.
- **Numbering:** Arabic `chapter.position` in the public title (confirmed by the user).
- **Storage:** stored token on `scenes` (revocable), not signed URLs (confirmed by the user).

## Deviations from the spec/plan

- **Task 01 — config shape:** used the whitelist config `scene_link_durations` (+
  `scene_link_default_duration`) from `00-overview.md` / the task file, **not** the single
  `scene_link_ttl` string sketched in `data-model.md`. The overview's owner-picks-per-share
  decision is binding and supersedes the older data-model draft. `data-model.md` left un-edited.
- **Task 01 — `shareUrl()` positive-case test deferred to task 03:** the non-null branch needs the
  `shared.scenes.show` route (registered in task 03), so `SceneShareTest` covers only the null
  (unshared) branch now, per the task file's explicit allowance. Task 03 should add the
  route-registered assertion.

## Task 02 — owner share management

### Deviations from the spec/plan

- **Controller write path:** `architecture.md` sketched `$scene->update([...])` for `store`/
  `destroy`, but `share_token` / `share_expires_at` are intentionally **not** `$fillable`, so
  `update()` would silently drop them. Used explicit property assignment + `save()` instead
  (`$scene->share_token = Str::random(48); …; $scene->save();`), which is what the task file and
  binding decision ("set explicitly, not mass-assigned") actually require. No `forceFill` needed —
  direct assignment already bypasses mass-assignment guarding.
- **Tests appended to the existing `tests/Feature/SceneShareTest.php`** (created by task 01) rather
  than a new file, matching the task file's named path. Added a `sceneFor(User)` helper that builds
  the project→act→chapter→scene graph (mirrors `SceneTest::chapterFor`).

### Issues → resolutions

- **Expiry assertion precision:** `share_expires_at` is a second-precision `timestamp` and the
  request round-trips through the HTTP stack, so an exact `Carbon::equalTo` against
  `now()->add('7 days')` is brittle. Asserted the two Unix timestamps are within a 60-second delta
  (`assertEqualsWithDelta`), matching the task file's "within a minute of `now()->add(...)`" note.
- **Guest → login redirect:** the `auth` middleware group already redirects unauthenticated POSTs
  to `route('login')`; the guest test asserts that redirect (not a 403) and that no token is set.

## Task 03 — public display page

### Deviations from the spec/plan

- **Expired page = `response()->view(..., status: 410)`, not `abort_unless($scene->isShared(), 410)`.**
  `architecture.md` sketched `abort_unless(..., 410)`, but the binding decision (overview + task
  file) is a *friendly branded* 410 page — a bare `abort(410)` renders the default error view. The
  controller returns the `shared.scenes.expired` view with a 410 status instead. The controller
  return type is therefore `View|Response`.
- **`<x-public-layout>` is a class-based component** (`App\View\Components\PublicLayout` →
  `layouts.public`), mirroring Breeze's existing `AppLayout`/`GuestLayout` convention, rather than
  an anonymous `components/public-layout.blade.php`. This is what makes `<x-public-layout>` resolve
  to `layouts/public.blade.php` (the path the task names) and keeps the layout-component pattern
  consistent with the rest of the app.
- **`shareUrl()` positive-case test landed here** (deferred from task 01, as task 01's log noted):
  `test_share_url_is_the_public_route_when_a_token_is_set` now that `shared.scenes.show` exists.

### Verification (beyond the green suite)

- Built assets (`npm run build`) succeed; no `public/hot` file exists, so `@vite` serves the build.
  A real server render (via `Kernel::handle` in tinker) confirmed the head emits
  `http://localhost/build/assets/app-*.css|js` (the build, **not** a `:5173` dev origin) and the
  `noindex, nofollow` robots meta; contents `**decisive**` rendered as `<strong>decisive</strong>`;
  the distinctive `notes` value did **not** appear; the expired case returned **410** with the
  "This share link has expired." copy and **no** scene name.
- **Manual click-path for the reviewer** (the one interactive bit tests can't drive here — no
  in-session browser): open a live share URL and click the **Description** header — the card should
  toggle open/closed (Alpine `x-data="{ open:false }"`, starts collapsed). The description markup is
  present in the server HTML regardless of the toggle state.

### Issues → resolutions

- **Dev DB missing the task-01 columns:** the manual tinker render first failed with
  `no such column: share_token` — the `add_share_columns_to_scenes_table` migration had only ever
  run against the in-memory test DB, never the local `database/database.sqlite`. Ran
  `php artisan migrate --force` to apply it locally; the render then succeeded. (Tests were always
  green because `RefreshDatabase` migrates fresh — this gap is invisible to the suite.)

## Task 04 — owner share UI on the scene edit page

### Deviations from the spec/plan

- **Regenerate carries a hidden default `duration`.** `store` (task 02) requires a whitelisted
  `duration`, but the spec/`ui.md` sketch the shared-state Regenerate as a bare button with no
  picker. Resolved by submitting a hidden `duration` set to the configured default
  (`scene_link_durations[scene_link_default_duration]`) so the re-POST validates. A per-regenerate
  duration select would be a nicety but exceeds the task's "re-POST store (rotates token)" scope.
- **Default-duration preselect resolves the key to its value.** `scene_link_default_duration` is a
  config *key* ('7 days') while the `<option value>` and the posted/`old()` value are the *value*
  string. The view computes `$shareDefaultDuration = $durations[$defaultKey] ?? reset($durations)`
  and matches `@selected(old('duration', $shareDefaultDuration) === $value)`, so the preselect is
  correct even if label/value ever diverge (they happen to be identical today).
- **No `x-cloak` in the app's CSS**, so the "Copied!" span uses `x-show="copied" style="display:
  none;"` (hidden pre-Alpine-mount, shown on click) — matching `wysiwyg.blade.php`'s documented
  convention of `style="display:none"` over `x-cloak`, rather than introducing an `[x-cloak]` rule.

### Verification (beyond the green suite)

- Feature tests GET the real `scenes.edit` route through the full controller and `assertSee` the
  server-rendered HTML in **both** states: unshared → "Generate share link" + every duration label +
  the store-route action + `value="7 days" selected`; shared → the public `shareUrl()`, the absolute
  expiry (`M j, Y H:i`), Regenerate/Revoke, the destroy-route action, and the copy button's
  `aria-label="Copy share link to clipboard"`. Non-owner GET → 403. So the rendered surface is
  observed, not just the test count.
- `npm run build` succeeds (✓ built in ~4s); no `public/hot`, so `@vite` serves `/build/assets/*`
  — no stale dev-server pointer. The share card adds no JS bundle (inline Alpine only).
- **Manual click-path for the reviewer** (the one bit tests can't drive — no in-session browser):
  on a shared scene's edit page, click **Copy** — the button should copy the URL to the clipboard
  and swap its label to "Copied!" for ~2s (Alpine `x-data="{ copied }"` +
  `navigator.clipboard.writeText`). Focusing the URL field also selects its text.

## Issues → resolutions

- **Timestamp precision in the migration test:** `share_expires_at` is a `timestamp` column
  (second precision), so `now()->addWeek()` did not round-trip via `Carbon::equalTo` (sub-second
  mismatch → false). Fixed by comparing `->toDateTimeString()` on both sides. A green suite would
  have hidden this only if the assertion were laxer; the strict equality caught it.
- **Mass-assignment tests rely on non-strict Eloquent:** `fill()` silently discards non-fillable
  keys (asserted `share_token`/`share_expires_at` stay null). Verified no `shouldBeStrict` /
  `preventSilentlyDiscardingAttributes` is enabled — if strict mode is later turned on, those
  tests would need `expectException(MassAssignmentException::class)` instead.
