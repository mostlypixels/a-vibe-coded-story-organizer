@props([
    'label',
    'item' => null,
    'fallbackUrl',
])

{{--
    Dashboard tile naming the single most recently edited entity of one kind:
    "Latest scene" over the scene's own name. The whole tile is the link — to
    the entity when there is one, to its index when the project has none yet.
--}}
<a
    href="{{ $item?->url ?? $fallbackUrl }}"
    class="block overflow-hidden rounded-lg bg-surface-raised p-4 shadow-xs hover:bg-surface-sunken">
    <x-heading level="6">{{ $label }}</x-heading>

    @if ($item)
        <p class="mt-1 truncate font-semibold text-content">{{ $item->label }}</p>
        <p class="mt-0.5 text-xs text-content-subtle">
            <time datetime="{{ $item->updatedAt->toIso8601String() }}" title="{{ $item->updatedAt->format('d F Y H:i') }}">
                {{ $item->updatedAt->diffForHumans() }}
            </time>
        </p>
    @else
        <p class="mt-1 text-sm text-content-muted">{{ __('Nothing yet') }}</p>
    @endif
</a>
