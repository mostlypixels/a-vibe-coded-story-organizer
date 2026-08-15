@props(['project', 'previous', 'next', 'numbering'])

{{-- Real <a> links so the pager stays keyboard-navigable; an end with no
     neighbour renders a disabled <span> instead of a href-less <a>, which
     browsers still treat as focusable and clickable. --}}
<nav aria-label="{{ __('Chapter navigation') }}" class="flex items-center justify-between gap-4 text-sm font-medium">
    @if ($previous)
        <a
            href="{{ route('projects.story.overview', ['project' => $project, 'chapter' => $previous->id]) }}#chapter-{{ $previous->id }}"
            class="inline-flex items-center gap-1 text-link hover:text-link-hover hover:underline"
        >
            <x-tabler-chevron-left class="h-4 w-4 shrink-0" aria-hidden="true" />
            <span>{{ __('Chapter :number', ['number' => $numbering->chapter($previous)]) }} &mdash; {{ $previous->name }}</span>
        </a>
    @else
        <span aria-disabled="true" class="inline-flex items-center gap-1 text-content-subtle cursor-not-allowed">
            <x-tabler-chevron-left class="h-4 w-4 shrink-0" aria-hidden="true" />
            {{ __('Previous chapter') }}
        </span>
    @endif

    @if ($next)
        <a
            href="{{ route('projects.story.overview', ['project' => $project, 'chapter' => $next->id]) }}#chapter-{{ $next->id }}"
            class="inline-flex items-center gap-1 text-link hover:text-link-hover hover:underline text-right"
        >
            <span>{{ __('Chapter :number', ['number' => $numbering->chapter($next)]) }} &mdash; {{ $next->name }}</span>
            <x-tabler-chevron-right class="h-4 w-4 shrink-0" aria-hidden="true" />
        </a>
    @else
        <span aria-disabled="true" class="inline-flex items-center gap-1 text-content-subtle cursor-not-allowed">
            {{ __('Next chapter') }}
            <x-tabler-chevron-right class="h-4 w-4 shrink-0" aria-hidden="true" />
        </span>
    @endif
</nav>
