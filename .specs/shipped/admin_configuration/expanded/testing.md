# Admin Configuration — Testing

Follow the existing style (`tests/Feature/*Test.php`): plain PHPUnit, `use RefreshDatabase`,
factories, `actingAs($user)`, and the `route()` helper — never raw URLs. Run with
`composer test` (in-memory SQLite).

There is an existing crawler-settings test to model on and, in part, migrate to the new route.
Find it first (`tests/Feature/CrawlerSetting*Test.php` or similar) and reuse its assertions.

## Shell + General settings (`AdminConfigurationTest` / adapt the crawler test)

- **Landing redirect:** `GET route('admin.index')` → redirect to `admin.settings.edit`.
- **General settings renders:** `GET admin.settings.edit` → `200`, contains the "General
  settings" heading and the search-engine form (checkbox + whitelist textarea).
- **Save still works:** `PATCH admin.settings.update` with a valid toggle/whitelist updates
  `CrawlerSetting::current()` and redirects with the `crawler-settings-updated` status — i.e.
  the *behaviour* the current crawler test asserts, now against the admin route. Assert
  `robots.txt` still reflects the change (guard against the relocation breaking the wiring).
- **Validation failure:** an invalid whitelist term (CR/LF, `:`, `#`) → `assertSessionHasErrors`
  (reuse the existing rule's cases; don't re-test the rule itself in depth).
- **Appearance placeholder:** `GET admin.appearance.edit` → `200`, has its heading, has **no**
  form/`<input>`.
- **Sidebar active state:** on each section page, assert the corresponding link carries
  `aria-current="page"` (mirrors the nav active-state tests; active state is not colour-only).

## Authorization (the negative case — mandatory)

Per CLAUDE.md, always cover the negative case.

- **Guest:** `GET admin.index` (and each section route) as a guest → redirect to `login`.
- **Access posture (Q1):**
  - If v1 keeps *any authenticated user* (recommended default): assert a second authenticated
    user (not the "owner") still gets `200` on the admin pages, and add a comment/test name
    making clear this is the **deliberate** continuation of the `CrawlerSetting` exception
    (so a later reader doesn't think it's a missing check).
  - If Q1 adds a role/gate: assert a non-admin authenticated user gets `403` on every admin
    route, and an admin gets `200`.
- Because these routes are not `Project`-owned, there is **no** ProjectPolicy 403 to test here
  — don't invent one.

## Export / import (only if Q3 approves building it)

- **Export happy path:** `POST admin.data.export` → a download response
  (`assertDownload()` / correct `Content-Disposition` + mime), non-empty body.
- **Export content:** seed a small project (factory) and assert the exported `data.json`
  contains its projects/scenes/codex entries (decode the artifact in the test).
- **Import validation:** `POST admin.data.import` with a non-zip / oversized / wrong-schema
  file → `assertSessionHasErrors` and **no** data change.
- **Import happy path:** round-trip — export, wipe/second user, import, assert the aggregate is
  restored **with invariants intact**:
  - exactly one `is_main` plotline per imported project (no duplicate),
  - `position` ordering preserved on acts/chapters/scenes,
  - attribute-timeline Start baseline present and `valueAt()` resolves,
  - rich-text fields are sanitised (inject a `<script>` in the artifact → assert it's stripped
    after import; import must not trust the file).
- **Import atomicity:** a malformed artifact mid-stream leaves the DB unchanged (transaction
  rolled back); any files written are cleaned up (post-commit disk rule).
- **Destructive import guard (if replace semantics):** import without the confirmation flag is
  rejected.

## Database configuration

- **Read-only page (v1):** `GET admin.database.edit` → `200`, shows the current driver/database
  name, and **does not** leak the DB password (assert the password string never appears in the
  HTML).
- If Q4 ever approves conversion: it must be tested at the **Artisan-command** level with an
  offline app, not as an HTTP action — out of scope for this test suite until specced
  separately.

## Coverage notes

- Add the new tests under `tests/Feature/`. Keep the crawler-settings behaviour covered under
  whichever route survives (Q2) — do not delete that coverage, relocate it.
- Assert route-name existence (`route('admin.settings.edit')` etc.) so a missing route fails
  loudly rather than 404-ing a link in the nav/sidebar.
