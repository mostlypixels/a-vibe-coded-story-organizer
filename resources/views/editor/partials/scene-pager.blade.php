{{-- Story order inside the book. A link is absent at the start or the end of the book. --}}
@if ($previous || $next)
    <nav aria-label="{{ __('Scene navigation') }}" class="flex items-center justify-between gap-4 text-sm font-medium" data-editor-pager>
        @if ($previous)
            <a
                href="{{ route('scenes.write', $previous) }}"
                class="inline-flex min-w-0 items-center gap-1 text-link hover:text-link-hover hover:underline"
                rel="prev"
                data-editor-previous
            >
                <x-tabler-chevron-left class="h-4 w-4 shrink-0" aria-hidden="true" />
                <span class="sr-only">{{ __('Previous scene:') }}</span>
                <span class="truncate">{{ $previous->name }}</span>
            </a>
        @else
            <span></span>
        @endif

        @if ($next)
            <a
                href="{{ route('scenes.write', $next) }}"
                class="inline-flex min-w-0 items-center gap-1 text-right text-link hover:text-link-hover hover:underline"
                rel="next"
                data-editor-next
            >
                <span class="sr-only">{{ __('Next scene:') }}</span>
                <span class="truncate">{{ $next->name }}</span>
                <x-tabler-chevron-right class="h-4 w-4 shrink-0" aria-hidden="true" />
            </a>
        @endif
    </nav>
@endif
