# Task 03 — Safe rendering (display component + view swaps)

## Scope

Render the now-sanitized rich HTML in the read views. Because task 02 guarantees stored content
is clean, rendering with `{!! !!}` is "intentionally rendering trusted HTML" per the guidelines.

**Builds:**
- `resources/views/components/rich-text.blade.php` (`x-rich-text`) — prop `html`; emits
  `{!! $html !!}` inside a `prose prose-sm max-w-none text-gray-700` container (the same
  Typography classes the Story overview uses). This is the **only** place rich user HTML is
  echoed with `{!! !!}`.
- An **excerpt** for list cells — either `x-rich-text-excerpt` or an `excerpt` prop — that emits
  an **escaped** `Str::of($html)->stripTags()->limit(120)` text (no `{!! !!}`), so `x-table`
  rows keep their layout.
- **View swaps** (replace `{{ $x->description }}` with the appropriate component):
  - Full render: `projects/show.blade.php`.
  - Excerpt in index tables: `acts/index`, `chapters/index`, `plotlines/index`, `events/index`,
    `scenes/index`, and `dashboard.blade.php`.

**Does NOT:** change the create/edit **form** inputs (still plain textareas until task 04),
touch the Story overview's `Str::markdown($scene->contents)` (Markdown-only invariant), or add
the editor.

## Depends on

- **02** (fields must be sanitized on write before anything renders them with `{!! !!}`).

## Key decisions already made (binding)

- `{!! !!}` on rich content **only** via `x-rich-text`; grep to confirm no other raw echo of a
  rich field exists.
- Index/list previews use the **escaped `stripTags`+`limit` excerpt**, never full HTML.
- Story overview rendering of `Scene.contents` is **unchanged**.

## Docs to consult

`ui.md` (components + which views), `architecture.md` §3, `security.md` (defense-in-depth
rendering rule).

## Tests to add

Extend the feature tests (HTTP-level):
- Show/detail page renders allowed formatting (`assertSee('<strong>', false)` for seeded rich
  content) and **does not** emit a script even if one was posted pre-sanitization
  (`assertDontSee('<script>', false)`).
- An index page containing rich descriptions renders the **text excerpt** (no raw tags leak into
  the cell) and still returns 200.
- Owner sees the content; authorization on the read routes is unchanged (non-owner behavior per
  existing `ProjectPolicy view`).

Run `composer test` and `vendor/bin/pint`.
