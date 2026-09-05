@props([
    'sort',
    'direction',
    'searchPlaceholder',
    'clearUrl',
    'createUrl',
    'createLabel',
    'filters' => ['search'],
])

<div class="flex items-center justify-between gap-4">
    <form method="GET" class="flex items-center gap-2">
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="direction" value="{{ $direction }}">
        <x-text-input type="text" name="search" placeholder="{{ $searchPlaceholder }}" class="text-sm" :value="request('search')" />

        {{ $slot }}

        <x-button variant="secondary" type="submit">{{ __('Filter') }}</x-button>
        {{-- filled(), not hasAny(): a filter present but blank must not show Clear. --}}
        @if (collect($filters)->contains(fn ($key) => request()->filled($key)))
            <a href="{{ $clearUrl }}" class="text-sm text-content-muted hover:text-content">{{ __('Clear') }}</a>
        @endif
    </form>

    <x-button variant="primary" :href="$createUrl">{{ $createLabel }}</x-button>
</div>
