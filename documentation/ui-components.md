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
