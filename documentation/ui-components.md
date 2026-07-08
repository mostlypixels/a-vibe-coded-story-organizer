# UI component library

Reusable, presentation-only Blade components under `resources/views/components/`. They give the
app a consistent look — roughly the set you'd reach for in Bootstrap (cards, buttons, badges,
alerts, breadcrumbs, tooltips, popovers, modals) plus a unified heading scale.

> [!NOTE]
> **Reuse before you build.** Check this page (and the existing components) before adding a new
> one. These are the shared vocabulary — a one-off inline style is a smell.

## Conventions they all follow

- Declared with `@props([...])`; extra HTML attributes flow through via `$attributes->merge([...])`,
  so you can always add `class`, `id`, `x-on:click`, etc. at the call site.
- Colours are written as **full Tailwind class strings** per variant. This is deliberate: Tailwind's
  purge step only keeps classes it can see as literal text, so you must never build a class name by
  interpolating a variant (e.g. `bg-{$color}-100` would be purged).
- Interactive components use **Alpine.js** and hide their initial state with `style="display: none;"`
  (matching `x-modal` / `x-dropdown`) rather than `x-cloak`, so no extra CSS is required.
- User-facing strings go through `{{ __('...') }}`; icons are inline 20×20 `currentColor` SVGs.

## Heading

Renders `<h1>`–`<h6>` on one shared typographic scale. Choose the level for **document semantics**
(the outline screen readers use); the size follows from the scale.

```blade
<x-heading level="1">Story Overview</x-heading>
<x-heading level="3">Chapter title</x-heading>
```

## Button

One button for every style. Renders an `<a>` when `href` is given, otherwise a `<button>`.

| Prop      | Values                                                                       | Default     |
|-----------|------------------------------------------------------------------------------|-------------|
| `variant` | `primary`, `secondary`, `danger`, `success`, `warning`, `ghost`, `link`      | `primary`   |
| `size`    | `sm`, `md`, `lg`                                                             | `md`        |
| `href`    | URL — renders an `<a>` instead of a `<button>`                               | `null`      |
| `type`    | `submit`, `button`, `reset` (buttons only)                                   | `submit`    |

```blade
<x-button variant="danger">Delete</x-button>
<x-button variant="secondary" type="button">Cancel</x-button>
<x-button variant="primary" size="lg" :href="route('projects.acts.create', $project)">New Act</x-button>
```

## Card

Surface container with an optional header and footer.

```blade
{{-- Simple title header --}}
<x-card title="Details">…body…</x-card>

{{-- Full-control header + footer slots --}}
<x-card>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <x-heading level="3">Acts</x-heading>
            <x-button size="sm" :href="route('projects.acts.create', $project)">New</x-button>
        </div>
    </x-slot>

    …body…

    <x-slot name="footer">
        <x-button variant="secondary">Close</x-button>
    </x-slot>
</x-card>
```

## Table

Card-wrapped, striped, sortable data table — the shared skeleton behind the plotline / event /
act / chapter / scene index pages. Four components work together:

- **`x-table`** — the card wrapper + `<table>`. Header cells go in the `head` slot (rendered as one
  `bg-sun-400` header row); body rows go in the default slot.
- **`x-table-heading`** — a non-sortable header cell (themed `bg-sun-400` / `text-navy-900`). Render
  it empty (`<x-table-heading />`) for a spacer column such as the trailing row-actions column. Its
  sortable counterpart is [`x-sortable-header`](../resources/views/components/sortable-header.blade.php),
  which shares the same cell styling and adds the sort link/arrow.
- **`x-table-row`** — a body row. Pass `:striped="$loop->even"` for zebra striping; striped rows use
  `bg-gray-100` (a step darker than the plain `bg-white` rows).
- **`x-table-empty`** — the full-width empty-state row for the `@empty` branch. It renders one of two
  messages so an empty table never reads as a bare "no results" line:
  - **genuinely empty** (`:filtered="false"`, the default) — friendly "No :items yet." copy plus, when
    `:create-url` and `:create-label` are given, a primary button pointing at the create action;
  - **hidden by a filter** (`:filtered="true"`) — a "No :items match your search or filters." line,
    with no call-to-action (the toolbar's own *Clear* link is the way back).

  | Prop           | Meaning                                                             | Default        |
  |----------------|--------------------------------------------------------------------|----------------|
  | `colspan`      | Cell span, matching the table's column count                       | `1`            |
  | `filtered`     | Whether a search/filter is currently applied (`request()->hasAny([...])`) | `false` |
  | `createUrl`    | `href` to the resource's create action (drives the empty-state CTA) | `null`        |
  | `createLabel`  | Already-translated CTA text, e.g. `__('New Act')`                   | `null`         |
  | `items`        | Already-translated plural noun for the copy, e.g. `__('acts')`      | `null`         |

  > [!NOTE]
  > Pass `items` already translated (`:items="__('events')"`); for the Codex it is the lowercased
  > type label, `\Illuminate\Support\Str::lower($type->pluralLabel())`. A slot overrides the default
  > empty headline if you need bespoke copy.

```blade
<x-table>
    <x-slot:head>
        <x-sortable-header field="name" :sort="$sort" :direction="$direction">{{ __('Name') }}</x-sortable-header>
        <x-table-heading>{{ __('Description') }}</x-table-heading>
        <x-table-heading />
    </x-slot:head>

    @forelse ($acts as $act)
        <x-table-row :striped="$loop->even">
            <td class="px-4 py-3 …">{{ $act->name }}</td>
            …
        </x-table-row>
    @empty
        <x-table-empty
            :colspan="3"
            :filtered="request()->filled('search')"
            :create-url="route('projects.acts.create', $project)"
            :create-label="__('New Act')"
            :items="__('acts')"
        />
    @endforelse
</x-table>
```

## Badge

Small status/label pill. For scene status specifically, use the domain-aware
[`x-scene-status-badge`](../resources/views/components/scene-status-badge.blade.php), which maps the
`SceneStatus` enum onto these same styles.

- `variant`: `gray` (default), `primary`, `info`, `success`, `warning`, `danger`, `indigo`
- `pill`: `true` (default, fully rounded) or `false` (slightly rounded)

```blade
<x-badge variant="success">Final</x-badge>
<x-badge variant="warning" :pill="false">3 chapters</x-badge>
```

## Alert

Contextual feedback banner with a leading icon.

- `variant`: `info` (default), `success`, `warning`, `danger`
- `title`: optional bold heading line
- `dismissible`: `true` adds a close button (Alpine hides the banner in place)

```blade
<x-alert variant="success" title="Saved">Your changes have been stored.</x-alert>
<x-alert variant="danger" dismissible>Something went wrong.</x-alert>
```

## Breadcrumbs

Data-driven trail. Pass an array of `['label' => …, 'url' => …]`; the last item (and any item
without a `url`) renders as plain current-page text.

```blade
<x-breadcrumbs :items="[
    ['label' => __('Projects'), 'url' => route('dashboard')],
    ['label' => $project->name, 'url' => route('projects.show', $project)],
    ['label' => __('Acts')],
]" />
```

## Tooltip

Wraps any trigger and shows a small label on hover **and** keyboard focus.

- `text`: the tooltip label (required)
- `position`: `top` (default), `bottom`, `left`, `right`

```blade
<x-tooltip text="Move up">
    <x-icon-move-up-button :action="route('acts.move-up', $act)" />
</x-tooltip>
```

## Popover

Click-toggled panel anchored to a trigger. Uses named `trigger` and `content` slots (like
`x-dropdown`); closes on outside click or Escape.

- `title`: optional header
- `position`: `bottom` (default), `top`, `left`, `right`
- `width`: any Tailwind width class, default `w-64`

```blade
<x-popover title="Help" position="bottom">
    <x-slot name="trigger">
        <x-button variant="ghost" size="sm" type="button">?</x-button>
    </x-slot>
    <x-slot name="content">
        Positions renumber automatically when you reorder.
    </x-slot>
</x-popover>
```

## Dialog (modal)

Higher-level modal with a header (title + close button), body, and optional footer, built on the
low-level [`x-modal`](../resources/views/components/modal.blade.php) shell. Open and close it by
dispatching browser events — `open-modal` with the dialog's `name`, and `close`.

```blade
<x-button type="button" x-on:click="$dispatch('open-modal', 'confirm-delete')">Delete</x-button>

<x-dialog name="confirm-delete" :title="__('Delete act?')">
    {{ __('This cannot be undone.') }}

    <x-slot name="footer">
        <x-button variant="secondary" type="button" x-on:click="$dispatch('close')">Cancel</x-button>
        <x-button variant="danger">Delete</x-button>
    </x-slot>
</x-dialog>
```

> [!NOTE]
> Use `x-dialog` for informational or confirmation modals. The delete icon buttons in index tables
> still use the simpler native `confirm()` dialog via `x-icon-delete-button`; both are fine.

## Rich text (WYSIWYG)

Three components make up the rich-HTML text feature. The full model — the field taxonomy, the
HTMLPurifier allow-list, and the security rules — lives in
[`documentation/rich-text.md`](rich-text.md); this is the component-level reference.

> [!WARNING]
> Rich HTML is safe to render only because it is sanitized **on write** (`HtmlSanitizer`
> set-mutators). Echo it with `{!! !!}` **only** through `x-rich-text`; never point `{!! !!}` at a
> rich field anywhere else. Index/list cells use the escaped `x-rich-text-excerpt`.

### `x-wysiwyg`

The rich-HTML editor input — a Tiptap editor with an always-visible formatting toolbar, layered
over a real `<textarea>` as progressive enhancement (a JS-off submit still works and `old()`
repopulates on failure). Replaces a plain `<textarea>` on every form with a rich-HTML field.

| Prop         | Meaning                                                        | Default        |
|--------------|----------------------------------------------------------------|----------------|
| `name`       | Form field name (required)                                     | —              |
| `id`         | Element id                                                     | `name`         |
| `value`      | Initial HTML value (e.g. `old('description', $model->description)`) | `''`      |
| `rows`       | Fallback textarea rows; also seeds the editor min-height       | `4`            |
| `minHeight`  | Explicit editor min-height (CSS length)                        | derived from `rows` |
| `placeholder`| Empty-state placeholder text                                   | `''`           |
| `disabled`   | Read-only (hides the toolbar)                                  | `false`        |

```blade
<x-wysiwyg
    name="description"
    :value="old('description', $project->description)"
    :placeholder="__('Describe this project…')"
/>
```

### `x-rich-text`

Renders sanitized rich HTML on detail/show pages — the **only** component that echoes a rich
field with `{!! !!}`. Renders nothing when `html` is blank; its `prose` classes match the Story
overview's Markdown output.

```blade
<x-rich-text :html="$project->description" />
```

### `x-rich-text-excerpt`

A short, **plain-text** preview of a rich field for index/list table cells: strips tags,
squishes whitespace, truncates, and renders escaped (`{{ }}`) — no markup leaks into a table row.

- `html`: the rich HTML value
- `limit`: max characters (default `120`)

```blade
<x-rich-text-excerpt :html="$plotline->description" :limit="80" />
```
