{{--
    Laravel's `pagination::tailwind` view, published so it can use the theme
    tokens. The stock file paints itself with fixed greys and a Tailwind `dark:`
    branch, which this project does not use — runtime tokens supply the whole
    palette for every preset (see documentation/interface/themes.md).

    Its "Showing x to y of z results" block is deliberately gone. `x-pagination-bar`
    prints the row range, on every list and at every row count; keeping this copy
    too would print it twice, and this one hides itself below the `sm` breakpoint.
--}}
@php
    $seat = 'relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium border border-border-strong leading-5';
    $arrow = 'relative inline-flex items-center px-2 py-2 -ml-px text-sm font-medium border border-border-strong leading-5';
    // focus:z-10 lifts the ring above the neighbouring seat it would otherwise sit under.
    $active = 'bg-surface-raised text-content-muted transition ease-in-out duration-150 hover:bg-surface-sunken focus:outline-hidden focus:ring-2 focus:ring-focus focus:z-10';
    $inert = 'bg-surface-raised text-content-subtle cursor-not-allowed';
    // The current page is `primary`, not `neutral`: under the light presets
    // `neutral` sits a few percent off `surface-raised` and the marker vanishes.
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}">

        <div class="flex gap-2 items-center justify-between sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center px-4 py-2 text-sm font-medium border border-border-strong rounded-md leading-5 bg-surface-raised text-content-subtle cursor-not-allowed">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center px-4 py-2 text-sm font-medium border border-border-strong rounded-md leading-5 bg-surface-raised text-content-muted transition ease-in-out duration-150 hover:bg-surface-sunken focus:outline-hidden focus:ring-2 focus:ring-focus">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center px-4 py-2 text-sm font-medium border border-border-strong rounded-md leading-5 bg-surface-raised text-content-muted transition ease-in-out duration-150 hover:bg-surface-sunken focus:outline-hidden focus:ring-2 focus:ring-focus">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="inline-flex items-center px-4 py-2 text-sm font-medium border border-border-strong rounded-md leading-5 bg-surface-raised text-content-subtle cursor-not-allowed">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>

        <span class="hidden sm:inline-flex rtl:flex-row-reverse shadow-xs rounded-md">

            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                    <span class="{{ $arrow }} {{ $inert }} ml-0 rounded-l-md" aria-hidden="true">
                        <x-tabler-chevron-left class="w-5 h-5" aria-hidden="true" />
                    </span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $arrow }} {{ $active }} ml-0 rounded-l-md" aria-label="{{ __('pagination.previous') }}">
                    <x-tabler-chevron-left class="w-5 h-5" aria-hidden="true" />
                </a>
            @endif

            {{-- Pagination Elements --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <span aria-disabled="true">
                        <span class="{{ $seat }} bg-surface-raised text-content-muted cursor-default">{{ $element }}</span>
                    </span>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page">
                                <span class="{{ $seat }} bg-primary text-primary-content cursor-default">{{ $page }}</span>
                            </span>
                        @else
                            <a href="{{ $url }}" class="{{ $seat }} {{ $active }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $arrow }} {{ $active }} rounded-r-md" aria-label="{{ __('pagination.next') }}">
                    <x-tabler-chevron-right class="w-5 h-5" aria-hidden="true" />
                </a>
            @else
                <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                    <span class="{{ $arrow }} {{ $inert }} rounded-r-md" aria-hidden="true">
                        <x-tabler-chevron-right class="w-5 h-5" aria-hidden="true" />
                    </span>
                </span>
            @endif
        </span>
    </nav>
@endif
