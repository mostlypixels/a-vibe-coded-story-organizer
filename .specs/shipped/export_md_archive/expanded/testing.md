# Export to static files — Testing

New file: `tests/Feature/ExportTest.php`. Plain PHPUnit, `use RefreshDatabase`, factories,
`actingAs($user)`, `route()` helper — the `ProjectTest.php` style (CLAUDE.md → Testing).
Tests run against in-memory SQLite via `composer test`. `ext-zip` must be enabled in the
test environment (see `data-model.md`).

## Reading the zip in a test

The export streams a `BinaryFileResponse` with `deleteFileAfterSend(true)`. In a test,
capture the bytes and open them with `ZipArchive`:

```php
$response = $this->actingAs($user)->post(route('admin.data.export'), [
    'project_id' => $project->id,
    'include_images' => '1',
]);
$response->assertOk();
$response->assertHeader('content-type', 'application/zip');

$tmp = tempnam(sys_get_temp_dir(), 'exporttest');
file_put_contents($tmp, $response->streamedContent()); // or ->getContent()
$zip = new ZipArchive();
$zip->open($tmp);
// assert entries via $zip->locateName(...) / $zip->getFromName(...)
```

Add a small private helper `assertZipHasEntry($zip, $name)` / `zipEntry($zip, $name)` to keep
assertions readable. Use `Storage::fake('public')` and `UploadedFile::fake()->image(...)` (or
seed `codex_media` rows against faked files) so image bytes exist to copy.

## Cases to cover

### Happy path & structure
- **Download shape**: 200, `Content-Type: application/zip`,
  `Content-Disposition` filename = `<project-slug>.zip`.
- **Story tree**: given an act (position 1, "The Beginning"), a chapter (position 1,
  "Arrival"), two scenes → assert entries:
  - `01-the-beginning/index.html`
  - `01-the-beginning/01-arrival/index.html`
  - `01-the-beginning/01-arrival/01-<slug>.html` **and** `…/01-<slug>.md`
  - second scene `02-<slug>.html` / `.md`
- **Root storyline**: `storyline.html` exists at the zip root and contains the scene prose
  (assert a substring of a scene's rendered `contents`).
- **Numbering follows position**: reorder scenes (swap positions) → filenames renumber
  accordingly. Guards the position-ordering invariant.

### Scene file contents
- Scene `.html` contains **all** fields: name, description (a distinctive rich-HTML
  substring, verbatim), contents (Markdown rendered to HTML — assert a `<p>`/`<em>` from
  Markdown), notes (rich-HTML substring), status label, and event title when set.
- Scene `.md` **starts with YAML frontmatter** (`---` fence) carrying the metadata keys
  (Q9), and the body is the **raw** Markdown `contents` (assert the raw `**bold**` marker is
  present, i.e. NOT converted to `<strong>`).

### Images toggle
- `include_images = 1`: `images/manifest.json` exists and is valid JSON; a cover file lands
  at its `images/…/cover/…` path with the **original filename**; the manifest row links it
  to the right `codex_entry_id` + `collection`.
- `include_images = 0` (and absent): **no** `images/` entries in the zip and no
  `manifest.json`. Assert `$zip->locateName('images/manifest.json') === false`.
- Original-format assertion: the exported image bytes equal the stored file bytes (no
  transform / thumbnail).

### Authorization (always cover the negative case)
- **Non-owner**: user A signed in, POSTs user B's `project_id` → **403**, and no download.
  (This is the critical admin-area-plus-ProjectPolicy check — see `architecture.md`.)
- **Unauthenticated**: POST without login → redirect to login (auth middleware).
- (Gate) A user who fails `access-admin` cannot reach the route — align with how the other
  admin-section tests assert the gate, if any exist.

### Validation
- Missing `project_id` → `assertSessionHasErrors('project_id')`.
- Non-existent `project_id` → 403 (ownership check in `authorize()` returns false for a
  missing project) — assert the exact behavior chosen in Q7 (403 vs 404 vs 422) and keep it
  consistent with `ExportRequest`.
- Non-boolean `include_images` (e.g. `"maybe"`) → `assertSessionHasErrors('include_images')`.

### Edge cases
- **Empty project** (no acts): 200, valid zip, `storyline.html` present (empty/placeholder
  body), no act folders, no error.
- **Empty/blank titles**: an act named `"!!!"` (slugs to empty) → filename falls back to
  `01-untitled` (Q4). Assert the fallback entry exists.
- **Chapter/scene with no scenes / no chapters**: act with an empty chapter still produces
  the chapter `index.html`; no scene files.
- **Scene with null `contents`**: `.md` has frontmatter + empty body; `.html` renders without
  error (mirror the `?? ''` guard in `story/index`).

## Not in scope for tests
- Import round-trip (Import tab is a stub).
- Concurrency / very large exports / memory ceilings (note in Q5 as a follow-up if a queued
  job is chosen instead of a synchronous download).
