# WYSIWYG textareas — Testing

Follow the existing style (`tests/Feature/ProjectTest.php`, `tests/Feature/SceneTest.php`):
plain PHPUnit, `use RefreshDatabase`, factories, `actingAs($user)`, the `route()` helper (never
raw URLs), in-memory SQLite, run via `composer test`. Cover happy path, authorization (owner
succeeds / non-owner 403), validation failure, and every invariant touched.

## Sanitization invariant (highest priority)

The security requirement must be proven by tests, not assumed.

- **`tests/Feature/HtmlSanitizationTest.php`** (or fold into per-model tests): for a
  representative rich field (e.g. `Act.description`), POST a payload with
  `<script>alert(1)</script>`, `<img src=x onerror=alert(1)>`, `<a href="javascript:alert(1)">bad</a>`,
  and a `style="..."` attribute. Assert the **stored** value (reload from DB) contains none of:
  `<script`, `onerror`, `javascript:`, `style=`. Assert allowed markup survives
  (`<strong>`, `<ul><li>`, `<a href="https://...">`).
- Assert the value rendered on the show/index page does not contain the script (defense in
  depth): `$this->get(route(...))->assertDontSee('<script>', false)`.
- Unit-level: a direct `HtmlSanitizer::clean()` test with a table of malicious inputs →
  expected clean outputs. Fast, and documents the allow-list.
- Because sanitization should run on the model write path (mutator/cast), a test that creates
  the model via Eloquent directly (not HTTP) should also come out clean — this guards the
  seeder/tinker path.

## Per-field feature tests

There are currently **no** feature tests for Acts, Chapters, Plotlines, Events (and Scenes
only recently). The guidelines say to add them as these areas are touched. Since every
`create`/`edit` view changes, add at least happy-path + authorization coverage where missing:

- Storing/updating a description with rich HTML persists sanitized HTML and redirects.
- Non-owner storing/updating → 403 (mirror-authorization in the Form Request).
- The Scene `contents` field still validates as Markdown (`ValidMarkdown`) and is **not**
  treated as HTML — a test asserting Markdown source is stored verbatim (not HTML-sanitized).

## Scene contents stays Markdown (regression guard)

- Assert `Scene.contents` still round-trips Markdown and renders via `Str::markdown()` on the
  Story overview (`StoryTest` if/when added). This is the spec's hard requirement — guard it.

## Image upload endpoint (only if it ships)

`tests/Feature/ProjectMediaUploadTest.php` using `Storage::fake()` and
`UploadedFile::fake()->image(...)`:

- **Owner uploads a valid image** → 200/201, JSON has a `url`, `Storage::assertExists`.
- **Anonymous** POST → redirect to login / 401 (`auth` middleware).
- **Logged-in non-owner** → 403 (`authorize('update', $project)`).
- **Invalid file** (`->create('x.php')`, or oversized) → 422, nothing stored.
- **Cleanup parity:** deleting the project removes the uploaded files off disk
  (`Storage::assertMissing`) — guards the cascade-bypasses-hooks invariant if a `project_media`
  table is added.

## Non-regression

- `composer test` green.
- `vendor/bin/pint` clean on new PHP.
- Manual/`/verify`: load an edit form, use a slash command, save, reopen → formatting
  preserved; disable JS → textarea still submits.
