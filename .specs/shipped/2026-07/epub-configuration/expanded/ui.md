# Epub Configuration — UI

## Page structure (three server-rendered views)

Each lives under `resources/views/admin/data/` inside `<x-admin-layout>`, opens with the
`Configuration` heading + the section heading, and `@include`s the shared sub-nav.

```
admin/data/
  partials/subnav.blade.php     ← 3 links: Export project | Export ebook | Import
  export-project.blade.php      ← the .zip form (moved out of today's index)
  export-ebook.blade.php        ← EPUB config form + export button (the new work)
  import.blade.php              ← upload form + in-progress imports (moved out)
```

Delete `admin/data/index.blade.php` once its three panels are split out. Carry the existing
`session('status')` flash block into whichever pages redirect back to them (import flows keep
it; the config-save flash lands on `export-ebook`).

### Sub-nav (`partials/subnav.blade.php`)

Links, not tabs — reuse the `sidebar.blade.php` active-state idiom (`aria-current="page"`,
flame border + `bg-aqua-50` when active). `role="tablist"`/Alpine roving-tabindex machinery from
the old card is **removed** — these are ordinary navigation links to distinct URLs.

```blade
@php $active = fn (string $r) => request()->routeIs($r); @endphp
<nav aria-label="{{ __('Export and import') }}" class="border-b border-gray-200 mb-6">
  <ul class="-mb-px flex gap-2">
    {{-- one <li><a aria-current…> per: admin.data.export-project /
         admin.data.export-ebook / admin.data.import.index --}}
  </ul>
</nav>
```

## Export-ebook config form

One `<form method="POST">` posting `@method('patch')` to
`admin.data.epub-settings.update` for the selected project, with a separate **Download EPUB**
form (or button) posting to `admin.data.export.epub` — mirror the existing pattern of two
independent forms rather than one shared Alpine picker (the grilled v1 decision). Recommended
layout: **Save configuration** persists settings; **Download EPUB** exports using the saved
settings. Interaction/ordering of the project selector across both is Q4.

Group the controls into `<x-card>`s / `<fieldset>`s with legends for accessibility:

1. **Project** — the `<select name="project_id">` (reuse the existing pattern; distinct id
   `epub_project_id`). Changing it should load that project's saved settings (Q4: full page
   reload via `GET ?project=` vs. Alpine — recommend a plain reload, no JS).

2. **Front & back matter** (Markdown textareas — reuse the app's Markdown affordance used for
   `Scene.contents`, **not** the `x-wysiwyg` editor, since these are Markdown):
   - Dedication, Acknowledgements, Preface, Postface — each a `<textarea>` + an
     `include_*` checkbox. Show `<x-input-error>` for `dedication` etc.
   - Helper text: "Markdown. Leave the box empty (or unchecked) to omit this page."

3. **Content options** (checkboxes):
   - Include project cover, Include scene titles, Include act descriptions, Include chapter
     descriptions, Include scene descriptions.

4. **Metadata** (checkboxes, each with the current stored value shown for context):
   - Include author / publisher / rights / ISBN. Render the underlying value inline
     ("Author: *Jane Doe*") so the author knows what the toggle emits, with a link to the
     project edit page to change the value itself.

5. **Formatting** (selects, each `<option>` from the enum's `label()`):
   - Chapter title format — show a live example per option (e.g. "Chapter 12: The Storm").
   - Table of contents depth — Acts / Chapters / Scenes.
   - Divider style — Horizontal rule / Decorative (Image disabled/omitted in v1).

6. **Appendix** (fieldset):
   - Include codex appendix (checkbox) gating the rest (Alpine `x-show` is fine here for
     progressive disclosure — a single small toggle, not a tab system).
   - Which entry types — a checkbox per `CodexEntryType` (`appendix_entry_types[]`).
   - Include images (checkbox).

Reuse existing components throughout: `<x-input-label>`, `<x-input-error>`, `<x-button>`,
`<x-card>`, `<x-heading>`. Do **not** invent new form components unless a control genuinely has
no existing equivalent (CLAUDE.md: reuse before creating).

## Keyboard & semantics

- Every checkbox/select has an associated `<label>`; grouped controls sit in `<fieldset><legend>`.
- The sub-nav is a real `<nav>` with `<a>` links (native tab order, no roving tabindex needed).
- Validation errors render with `<x-input-error :messages="$errors->get('field')">` and the field
  keeps its `old()` value.
- The "Include author/publisher/…" context values are escaped (`{{ }}`) — never `{!! !!}`.

## New EPUB Blade views (rendered by `EpubExporter`, not browser views)

Under `resources/views/exports/epub/`, matching the existing `title`/`act`/`chapter`/`toc`
pages (each `@extends('exports.epub.layout')`, XML-well-formed, self-closed voids):

- `dedication.blade.php`, `acknowledgements.blade.php`, `preface.blade.php`, `postface.blade.php`
  — each renders a heading + the field's Markdown-compiled HTML (`{!! $rendered !!}`, where
  `$rendered` came from the service's SmartPunct converter).
- `appendix-entry.blade.php` — entry name heading, optional embedded image, the entry's
  (normalised-to-XHTML) description HTML.
- Edit `chapter.blade.php` — accept `showSceneTitles`, `chapterDescriptionHtml`,
  `sceneDescriptions`, `dividerHtml`, and the pre-formatted heading string; branch on them.
- Edit `act.blade.php` — optional `descriptionHtml`.
- Edit `toc.blade.php` — optional third (scene) level.
- Edit `styles.css` — a `.divider` ornament rule for the decorative divider; appendix/front-matter
  page styles.
