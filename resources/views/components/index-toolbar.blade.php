@props([
    'sort',
    'direction',
    'searchPlaceholder',
    'clearUrl',
    'createUrl',
    'createLabel',
    'filters' => ['search'],
])

{{-- Wrap, so a phone does not push the buttons off the screen. A select is as wide as
     its longest option, so it gets a cap too: long chapter names widened the page.
     The open list still shows the full names. --}}
<div class="flex flex-wrap items-center justify-between gap-4">
    <form method="GET" class="flex flex-wrap items-center gap-2 min-w-0 max-w-full [&_select]:max-w-full sm:[&_select]:max-w-sm">
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="direction" value="{{ $direction }}">
        <x-text-input type="text" name="search" placeholder="{{ $searchPlaceholder }}" aria-label="{{ $searchPlaceholder }}" class="text-sm" :value="request('search')" />

        {{ $slot }}

        <x-button variant="secondary" type="submit">{{ __('Filter') }}</x-button>
        {{-- filled(), not hasAny(): a filter present but blank must not show Clear. --}}
        @if (collect($filters)->contains(fn ($key) => request()->filled($key)))
            <a href="{{ $clearUrl }}" class="text-sm text-content-muted hover:text-content">{{ __('Clear') }}</a>
        @endif
    </form>

    <x-button variant="primary" :href="$createUrl">{{ $createLabel }}</x-button>
</div>
