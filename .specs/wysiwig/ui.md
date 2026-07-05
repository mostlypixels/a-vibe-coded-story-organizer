# WYSIWYG textareas — UI

## New components

### `x-wysiwyg` (editor)

`resources/views/components/wysiwyg.blade.php` — the single reuse point that replaces the
rich-HTML textareas. Follows the component conventions in `documentation/ui-components.md`:
`@props([...])`, `$attributes->merge(...)` passthrough, Alpine for behavior, `{{ __() }}` for
strings.

- **Props:** `name`, `id` (default `name`), `value` (current HTML, defaults via `old()` at the
  call site), `rows`/`min-height`, `placeholder`, `disabled`.
- **Markup (progressive enhancement):** a real `<textarea>` holding the HTML value, plus a
  mount point the editor attaches to. Alpine boots the editor, hydrates it from the textarea,
  and syncs edits back into the textarea on input so the normal form submit carries the value.
  With JS off, the textarea submits raw (still sanitized server-side).
- **Styling:** reuse the existing input chrome (`border-gray-300 focus:border-ocean-500
  focus:ring-ocean-500 rounded-md shadow-sm`) on the editor container so it looks like the
  other fields. The editing surface uses `prose prose-sm max-w-none` to match how rich content
  renders elsewhere.

Call-site shape (replacing the current `<textarea>` blocks), e.g. in `acts/edit.blade.php`:

```blade
<div>
    <x-input-label for="description" :value="__('Description')" />
    <x-wysiwyg id="description" name="description" :value="old('description', $act->description)" />
    <x-input-error :messages="$errors->get('description')" class="mt-2" />
</div>
```

### `x-rich-text` (display)

`resources/views/components/rich-text.blade.php` — renders sanitized HTML for read views.

- **Prop:** `html`. Emits `{!! $html !!}` inside a `prose prose-sm max-w-none text-gray-700`
  container (same classes the Story overview uses for Markdown). Used anywhere a full
  description is shown (e.g. `projects/show`).
- **Excerpt variant** for index tables: a `stripTags`+`limit` text excerpt (no `{!! !!}`), so
  the striped `x-table` rows keep their layout. Either a second component
  (`x-rich-text-excerpt`) or a prop on the same one. Used in `acts/index`, `chapters/index`,
  `plotlines/index`, `events/index`, `scenes/index`, and `dashboard`.

## Views to change

Replace the rich-HTML `<textarea>`s with `<x-wysiwyg>`:

- `resources/views/projects/create.blade.php`, `projects/edit.blade.php` — `description`
- `resources/views/acts/create.blade.php`, `acts/edit.blade.php` — `description`
- `resources/views/chapters/create.blade.php`, `chapters/edit.blade.php` — `description`
- `resources/views/plotlines/create.blade.php`, `plotlines/edit.blade.php` — `description`
- `resources/views/events/create.blade.php`, `events/edit.blade.php` — `description`
- `resources/views/scenes/create.blade.php`, `scenes/edit.blade.php` — `description` and
  `notes` (if `notes` becomes HTML — see `open-questions.md`)
- `resources/views/codex/partials/fields.blade.php` — `description` (drop the "(Markdown)"
  label + `font-mono`; relabel as rich text)

**Do NOT change** the `scenes` `contents` textarea — it stays Markdown (keep label
"Contents (Markdown)", keep `font-mono`).

Update read/preview views to use the display component:

- `resources/views/projects/show.blade.php` — full `x-rich-text` for `description`.
- `resources/views/{acts,chapters,plotlines,events,scenes}/index.blade.php` and
  `dashboard.blade.php` — swap `{{ $x->description }}` for the excerpt variant.

## Editor behavior (slash commands)

Implement Redactix's out-of-the-box slash menu for HTML fields: headings, bold/italic/
underline/strike, bulleted + numbered lists, blockquote, inline code + code block, links,
horizontal rule. **Image galleries are not a priority**; a single inline image (via the
authenticated upload endpoint) is optional (see `open-questions.md`). Whatever the slash menu
can produce must be within the sanitizer allow-list (`security.md`) — keep the two lists in
sync.

## Accessibility & UX

- Keyboard accessibility is a guideline requirement: the editor must be focusable and operable
  by keyboard; the slash menu reachable/dismissible via keyboard. Verify with the chosen lib.
- Preserve `old()` repopulation on validation failure (the textarea holds the value; the
  editor hydrates from it) so a rejected save doesn't lose the author's work.
- Show `x-input-error` under the field exactly as today; sanitizer/validation errors surface
  there.
- No `x-cloak` — hide pre-mount state with `style="display:none"` like the other interactive
  components, to avoid a flash of the raw textarea.

## Assets

- Editor JS/CSS imported in `resources/js/wysiwyg.js` ← `resources/js/app.js`; editor CSS via
  `resources/css/app.css` if the lib ships styles. Bundled by Vite (`npm run build`). Keep the
  editor styles from fighting the Tailwind `prose` output.
