# Task 02 — Backend Markdown-converter consistency

## Scope

Bring the two hand-built Markdown converters (`ValidMarkdown`, `EpubExporter`'s private
`converter()`) into agreement with `Scene::renderedContents()` (which already uses
`Str::markdown()` → `GithubFlavoredMarkdownConverter`, confirmed by reading
`vendor/laravel/framework/.../Str.php` — no change needed there):

- **`app/Rules/ValidMarkdown.php`**: switch its bare `CommonMarkConverter` to
  `League\CommonMark\GithubFlavoredMarkdownConverter` (GFM includes strikethrough and
  task lists). This is a drop-in constructor swap — same `convert()` call, same
  exception-to-`$fail` handling.
- **`app/Services/EpubExporter.php`**: in the private `converter()` method, add
  `League\CommonMark\Extension\Strikethrough\StrikethroughExtension` and
  `League\CommonMark\Extension\TaskList\TaskListExtension` to the existing environment
  (alongside the already-present `SmartPunctExtension`). Keep the converter's isolation
  from `Scene::renderedContents()` intact — this is "add matching extensions to a
  separate instance," not "share the instance."

No PHP `composer.json` changes: both extension classes are already present under
`vendor/league/commonmark/src/Extension/` (`league/commonmark ^2.8` already installed).

## Depends on

None. Independent of task 01 (allow-list) and all JS tasks — this only touches
Markdown-side converters, not the HTML sanitizer or the editor.

## Key decisions already made

- Prefer `GithubFlavoredMarkdownConverter` over hand-adding
  `StrikethroughExtension`/`TaskListExtension` to a bare `CommonMarkConverter` for
  `ValidMarkdown`, specifically to keep it on the same grammar as
  `Scene::renderedContents()` (validation should recognize a superset no narrower than
  what rendering already accepts) — per `../expanded/architecture.md`.
- `EpubExporter` gets targeted extensions added to its *own* converter, not switched to
  `GithubFlavoredMarkdownConverter` wholesale — its docblock's SmartPunct isolation
  rationale must survive.

## Docs to consult

- `../expanded/architecture.md` (`ValidMarkdown`/`EpubExporter` sections).
- `../expanded/spec.md`'s "Strikethrough" bullet under "Rough approach" for the full
  four-surface-agreement rationale (editor, validator, shared render path, EPUB export).

## Tests

- New or extended `tests/Unit/Rules/ValidMarkdownTest.php` (check for an existing file
  first): a scene body containing `~~struck~~` and a GFM task-list checkbox
  (`- [ ] todo`) both pass validation. Note in the test that these were never *rejected*
  before — the fix is meaning, not acceptance (tildes were inert text to bare
  CommonMark, not invalid).
- Extend `tests/Unit/Services/EpubExporterTest.php`: a scene with `~~struck~~` content
  renders as struck-through markup (confirm the exact tag `StrikethroughExtension`
  emits — `<del>` per the League CommonMark GFM spec) in the exported EPUB's XHTML,
  where today it renders literal tildes. Same assertion shape for a task-list checkbox
  rendering as a real list-with-checkbox, not literal `[ ]` text.
