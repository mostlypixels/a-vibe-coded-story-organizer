# 07 — Epub export UI

## Scope

- `resources/views/admin/data/index.blade.php`, inside the existing `panel-export` tab panel,
  after the current zip-export `<form>`: a new "Epub export" section — heading, short
  description, its own `<form method="POST" action="{{ route('admin.data.export.epub') }}">`
  with a `project_id` select (independent of the existing form's picker — no shared Alpine
  state, per the grilled decision) and a submit button.
- Directly under the new form, a short note with a link to
  https://www.w3.org/publishing/epubcheck/ recommending authors run the official validator
  themselves for full conformance confidence (`target="_blank" rel="noopener"`).
- The existing `$projects->isEmpty()` empty-state branch already covers the whole panel — the
  new section only needs to render inside the existing non-empty branch, not duplicate the
  empty-state message.

## Explicitly not in scope

- Anything about the Project edit form's metadata fields (task 02) — this task only touches
  the admin export page.

## Depends on

06 (the route must exist to post to).

## Key decisions already made

- Two independent forms/pickers on the page, not a shared one — simplicity over a small
  amount of duplication (grilled decision, `open-questions.md` question 4).
- The epubcheck link is UI copy only — no automated validation is triggered from this button;
  see task 05 for what validation *does* run automatically.

## Docs to consult

- `expanded/ui.md` — the exact section/form/link markup shape.
- `resources/views/admin/data/index.blade.php` — the existing tab/panel structure and
  accessibility patterns (`role="tab"`, focus rings) to match.

## Tests

- Extend or add to whatever feature test covers `admin/data/index` (e.g. an
  `AdminDataExportPageTest` or similar, or add assertions to `EpubExportTest` from task 06):
  the export page renders the new "Epub export" section, its form, and the epubcheck link
  (`assertSee`/`assertSeeText` for the link text and `href`).
- Manual verification (per CLAUDE.md's "test the feature in a browser" and the skill's own
  guidance): after this task, actually click through the flow in a browser — pick a project,
  download the epub, open it in a real e-reader — and, per the earlier grill's decision, this
  is also the point to optionally run the real `epubcheck` tool locally against a sample
  output before considering the feature done.
