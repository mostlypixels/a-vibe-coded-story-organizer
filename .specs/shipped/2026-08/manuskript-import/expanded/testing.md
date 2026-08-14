# Testing

`tests/Feature/ManuskriptImportCommand.php`, plain PHPUnit + `RefreshDatabase`, driving the command
through `$this->artisan(...)` — no HTTP, no `actingAs`, no 403 case (there is no route and no
policy).

## Fixture

`tests/Fixtures/manuskript/` — a hand-trimmed project, checked in, small enough to read in a diff:

* `MANUSKRIPT`, `infos.txt` (title + author), plus one ignored `plots.xml` and one loose
  `outline/99-TODO.md`.
* 2 chapters (`00-…`, `10-…`, to prove numeric sort and the position renumbering), one with 2
  scenes and one with 1.
* One scene with a `title:`, one without (filename fallback), one with a multi-paragraph Markdown
  body and CRLF line endings.
* 2 characters: one richly filled with a multi-line `Notes:`, one that is all `?` except `Name`.

## Cases

* Happy path: project name/author, one act, chapter and scene counts, and the exact ordered lists of
  chapter and scene names.
* `position` is contiguous 1..n despite the source's `00`/`10` prefixes.
* Scene `contents` is the Markdown body verbatim, LF-normalized, with the header block absent.
* Character description contains `<h3>Last name</h3>` and its value; contains no heading for a `?`
  field; the all-`?` character imports with a name and a description that is null or empty.
* Multi-line `Notes:` keeps its lines as `<br>`, and its HTML survives the `SanitizesRichHtml`
  mutator unchanged (read the row back and compare).
* Skips are counted and named in the output (`assertSee` on the loose `outline/99-TODO.md`).
* Missing `MANUSKRIPT` marker, and a nonexistent path: `FAILURE`, and `Project::count()` unchanged.
* `--user` resolves by ID and by email; ambiguous/absent user with several users in the DB is a
  `FAILURE`.
* Failure rolls back: force a mid-import failure (a scene body containing raw `<script>`, caught by
  `ContentSanitizer`) and assert no project, act, chapter, scene or codex entry survives.
* Unit-level coverage of `ManuskriptFile`: continuation lines, a header with no body, a body
  containing a line that looks like a header (`Note: something` mid-prose must stay in the body).

Do not test against `D:\1-WRITING\…` — the real project is not a fixture and is not on CI.
