# Breadcrumbs — UI

## New component: `x-breadcrumbs`

`resources/views/components/breadcrumbs.blade.php`, `@props(['items'])` where `items` is
the `Breadcrumbs` iterable of `Crumb`.

W3C breadcrumb pattern — non-negotiable structure:

```blade
<nav aria-label="{{ __('Breadcrumb') }}">
  <ol class="flex items-center gap-2 ...">
    @foreach ($items as $crumb)
      <li class="flex items-center gap-2">
        @if ($crumb->url)
          <a href="{{ $crumb->url }}">{{ $crumb->label }}</a>
        @else
          <span @if($crumb->current) aria-current="page" @endif>{{ $crumb->label }}</span>
        @endif
        @unless ($loop->last)
          <span aria-hidden="true">›</span>   {{-- separator: decorative, not read --}}
        @endunless
      </li>
    @endforeach
  </ol>
</nav>
```

- Separator is CSS/`aria-hidden` decoration, never a list item — screen readers hear only
  the labels. Use a Tabler chevron (`x-tabler-chevron-right`) for visual consistency with
  the nav, not a literal `›`, if simpler.
- Links use the band's existing `text-nav-content` treatment (the header band already sets
  `[&_a]:text-nav-content`); current crumb is muted/non-interactive.
- Long leaf labels (entry names) truncate with `truncate max-w-*`; the trail never wraps to
  a second line on desktop. On mobile, allow horizontal scroll rather than wrap.

## Header band (`layouts/app.blade.php`)

Replace the single-child header `<div class="py-3 px-4">{{ $header }}</div>` with a
two-column row:

```blade
<div class="py-3 px-4 flex items-center justify-between gap-4">
  <div class="min-w-0">      {{-- left: breadcrumbs (min-w-0 lets truncate work) --}}
    <x-breadcrumbs :items="$breadcrumbs" />
  </div>
  <div class="shrink-0">{{ $headerActions ?? '' }}</div>  {{-- right: reserved, empty --}}
</div>
```

Render this band when `! $breadcrumbs->isEmpty()`. Else fall back to the current
`@isset($header)` band unchanged. So one `@if/@else` in the layout; project pages get
breadcrumbs, everything else keeps its slot.

`$headerActions` is a named slot on `x-app-layout` reserved for a future spec — declared,
documented as intentionally empty, no current callers.

## Heading / `<h1>` note

The old band carried the page `<x-heading level="2">`. Breadcrumbs are a `<nav>`, not a
heading. Confirm each converted page still has a document heading in its body (most edit
forms and index tables already do). Where a page relied solely on the header-band title for
its visible heading, add a body heading. Flag per-page in testing.

## Reuse

- `x-tabler-chevron-right` — already used across the nav.
- Header band colours (`text-nav-content`) already defined on `<header>` in the layout;
  the component inherits them, no new palette.
- No new Alpine, no JS. Pure server-rendered markup.
