# Epub export (v1) — UI

## Export page — new section

`resources/views/admin/data/index.blade.php`, inside the existing `panel-export` tab panel
(after the current zip export `<form>`, not replacing it): a new "Epub export" section —
its own heading, its own short description, its own `<form method="POST"
action="{{ route('admin.data.export.epub') }}">` with just a `project_id` select (same
pattern as the existing form; consider whether the two forms should share one project
picker via Alpine state, or stay fully independent — flag in `open-questions.md`) and a
submit button (e.g. "Download EPUB").

Directly under this new form, a short note:

```blade
<p class="mt-4 text-xs text-gray-500">
    {{ __('For full EPUB conformance verification, validate the downloaded file with the official') }}
    <a href="https://www.w3.org/publishing/epubcheck/" class="text-ocean-600 underline hover:text-ocean-800" target="_blank" rel="noopener">
        epubcheck
    </a>
    {{ __('tool.') }}
</p>
```

Empty-project state (`$projects->isEmpty()`) is already handled once for the whole panel —
the new section only needs to render inside the existing `@else` branch, not duplicate the
empty-state message.

## Project edit form — new metadata fields

Wherever the Project edit form currently lives (`name`, `description` — locate via
`resources/views` in the plan stage; not directly explored during this grill), add a
collapsible or clearly-labeled "Book metadata" subsection with:

- `language` — text input (or a small `<select>` of common BCP-47 codes with a free-text
  fallback — decide in planning), required, default `en`.
- `author` — text input, optional.
- `publisher` — text input, optional.
- `rights` — textarea, optional.
- `isbn` — text input, optional, with a hint like "ISBN-13, with or without hyphens".
- `cover_image` — file input, copied from `resources/views/codex/partials/fields.blade.php`'s
  existing cover-image pattern (image preview `<img>`, "remove" checkbox
  `remove_media[]`-equivalent, Tailwind `file:` input classes, `<x-input-error>`).

All six fields are optional metadata edits on the existing Project edit screen, not a new
page — consistent with "Project has only `name`/`description` today" being extended in place.

## Accessibility (of the UI itself, distinct from the epub's accessibility metadata)

- The new epub form's submit button and the epubcheck link must both be keyboard-reachable
  and have visible focus states, matching the existing tab/form patterns already in
  `admin/data/index.blade.php` (e.g. `focus:ring-2 focus:ring-ocean-500`).
- The cover-image file input reuses the Codex partial's existing accessible labeling
  (`<x-input-label>`, `<x-input-error>`) rather than inventing new markup.
