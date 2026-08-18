@props(['title'])

<section class="flex h-full flex-col rounded-lg border border-border">
    <header class="border-b border-border bg-surface-sunken px-4 py-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-sm font-semibold text-content-muted">{{ $title }}</span>
            {{ $badge ?? '' }}
        </div>

        @isset($meta)
            <p class="mt-0.5 text-xs text-content-muted">{{ $meta }}</p>
        @endisset
    </header>

    <div class="max-h-96 flex-1 overflow-auto px-4 py-3">
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="border-t border-border px-4 py-3">
            {{ $footer }}
        </footer>
    @endisset
</section>
