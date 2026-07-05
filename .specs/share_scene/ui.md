# Share scene — UI

## 1. Public display page — `resources/views/shared/scenes/show.blade.php`

A minimal, read-only page for an unauthenticated visitor. Do **not** use `x-app-layout`
(it renders the nav bar and assumes an authenticated user).

### Layout

- Reuse/extend `layouts/guest.blade.php` (already exists for Breeze auth screens) or add a
  slim `layouts/public.blade.php` (`<x-public-layout>`) if the guest layout's centered-card
  styling doesn't fit a long-form reading page. A reading page wants a wide, comfortable prose
  column (`max-w-3xl mx-auto`), so a dedicated public layout is likely cleaner.
- The layout `<head>` must include `<meta name="robots" content="noindex, nofollow">`.
- Include the compiled CSS (`@vite`) so Tailwind Typography (`prose`) and app styles apply.

### Structure

```blade
<x-public-layout>
    <div class="max-w-3xl mx-auto px-4 py-10 space-y-6">
        {{-- Formatted title: "Chapter 1 — Chapter title: Scene title" --}}
        <h1 class="text-3xl font-bold text-gray-900">
            {{ __('Chapter :number', ['number' => $scene->chapter->position]) }}
            &mdash; {{ $scene->chapter->name }}: {{ $scene->name }}
        </h1>

        {{-- Description in a COLLAPSED card (Alpine, mirrors Story overview toggles) --}}
        @if (filled($scene->description))
            <div x-data="{ open: false }" class="bg-white shadow-sm rounded-lg">
                <button type="button" @click="open = ! open"
                        class="w-full flex items-center justify-between px-6 py-4 text-left">
                    <span class="font-semibold text-gray-800">{{ __('Description') }}</span>
                    {{-- reuse the chevron svg from story/index.blade.php --}}
                </button>
                <div x-show="open" x-transition class="px-6 pb-4">
                    <x-rich-text :html="$scene->description" />
                </div>
            </div>
        @endif

        {{-- Contents rendered as formatted HTML (Markdown → HTML) --}}
        <article class="prose prose-sm max-w-none text-gray-700 text-justify [&_p]:my-4">
            {!! Str::markdown($scene->contents ?? '') !!}
        </article>
    </div>
</x-public-layout>
```

- **Collapsed by default:** `x-data="{ open: false }"` (spec says "collapsed card"). The Story
  overview uses `open: true`; here it starts closed.
- **Prose classes** match `story/index.blade.php` line 93 for visual consistency.
- **Never render `$scene->notes`** — it is intentionally absent from this template.
- Reuse the chevron SVG already present in `story/index.blade.php`; consider extracting it into
  an `x-chevron` component if it is copied a third time (DRY — but only on the third use).

### Expired / not-found response

- Simplest: `abort(410)` / `abort(404)` from the controller and rely on default error pages.
- Nicer (open question): a friendly `shared/scenes/expired.blade.php` on the public layout
  ("This share link has expired.") returned with a 410 status.

## 2. Owner management UI — on the scene edit page

Add a **"Share this scene"** section to `resources/views/scenes/edit.blade.php` (e.g. after the
delete form, or in its own `x-card`). Two states driven by `$scene->isShared()`:

### Not shared

```blade
<form method="POST" action="{{ route('scenes.share.store', $scene) }}">
    @csrf
    <x-primary-button>{{ __('Generate share link') }}</x-primary-button>
    <p class="text-sm text-gray-500">
        {{ __('Creates a public link, valid for :ttl.', ['ttl' => config('sharing.scene_link_ttl')]) }}
    </p>
</form>
```

### Shared (active link)

- Show the URL in a read-only `x-text-input` with a **Copy** button (small Alpine
  `navigator.clipboard.writeText` handler — keep JS inline and minimal, matching the app's
  progressive-enhancement style).
- Show expiry: `{{ $scene->share_expires_at->diffForHumans() }}` /
  `{{ $scene->share_expires_at->format('M j, Y H:i') }}`.
- **Regenerate** (re-POST `scenes.share.store` — rotates token) and **Revoke**
  (DELETE `scenes.share.destroy`) buttons, each its own small form with `@csrf`/`@method`.
- Use `x-secondary-button` / `x-danger-button` for regenerate/revoke to match existing button
  components.

> [!NOTE]
> Reuse existing components: `x-card`, `x-primary-button`, `x-secondary-button`,
> `x-danger-button`, `x-text-input`, `x-input-label`. No new table/icon components are needed.

## Accessibility

- The collapse toggle is a real `<button>` with visible text (keyboard-operable), matching the
  Story overview pattern.
- Copy button needs an accessible label (`aria-label`/`sr-only` text) and a visible
  confirmation ("Copied!") on click.
- Semantic HTML: `<h1>` for the title, `<article>` for contents.

## Nav / entry points

- No nav changes for the public page (it is link-only, and the public layout has no nav).
- The owner entry point is the scene edit page share section; optionally a share icon in the
  scenes index row is a future nicety, not required by the spec.
