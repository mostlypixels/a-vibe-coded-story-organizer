@props(['title'])

{{--
    The bordered, titled pane the compare page is built out of: the diff and each
    of the two whole-value columns beside it.

    Three panes that mean three different things — what moved, what it was, what
    it became — read as one comparison only if they are the same object. Sharing
    the shell is what makes "the diff" a labelled thing rather than loose text
    floating above two labelled boxes.

    Slots: `title` (prop), an optional `badge` beside it, an optional `meta` line
    under it, the body, and an optional `footer` for the pane's action.

    The body scrolls (`max-h-96`) instead of growing. Two full scene contents at
    natural height would push this page's revert buttons a novel's length apart,
    and comparing the columns would mean scrolling one past the other.
--}}
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
