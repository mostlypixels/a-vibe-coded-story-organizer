@props([
    'title',
    'items',
    'allUrl',
    'allLabel' => null,
    'noun' => null,
    'showCovers' => false,
])

<x-card :title="$title" stretch flush-footer :padded="false">
    @if (count($items) === 0)
        <p class="px-6 py-4 text-sm text-content-muted">
            {{ __('No :items yet.', ['items' => $noun ?? __('entries')]) }}
        </p>
    @else
        <ul class="divide-y divide-border">
            @foreach ($items as $item)
                <li class="flex items-center gap-3 px-6 py-3">
                    @if ($showCovers)
                        <a href="{{ $item->url }}" class="shrink-0" tabindex="-1" aria-hidden="true">
                            @if ($item->imageUrl)
                                <img src="{{ $item->imageUrl }}" alt="" class="h-10 w-10 rounded-sm border border-border object-cover">
                            @else
                                <span class="block h-10 w-10 rounded-sm border border-border bg-surface"></span>
                            @endif
                        </a>
                    @endif

                    <span class="min-w-0 flex-1">
                        <a href="{{ $item->url }}" class="font-medium text-content hover:text-link">{{ $item->label }}</a>
                        @if ($item->contextSegments)
                            <span class="flex min-w-0 items-center gap-1 text-xs text-content-subtle">
                                @foreach ($item->contextSegments as $segment)
                                    @unless ($loop->first)
                                        <x-tabler-chevron-right class="h-3.5 w-3.5 shrink-0 opacity-50" aria-hidden="true" />
                                    @endunless
                                    <span class="{{ $loop->last ? 'truncate' : 'shrink-0 whitespace-nowrap' }}">{{ $segment }}</span>
                                @endforeach
                            </span>
                        @elseif ($item->context)
                            <span class="block truncate text-xs text-content-subtle">{{ $item->context }}</span>
                        @endif
                    </span>

                    <time
                        datetime="{{ $item->updatedAt->toIso8601String() }}"
                        title="{{ $item->updatedAt->format('d F Y H:i') }}"
                        class="shrink-0 text-xs whitespace-nowrap text-content-muted">
                        {{ $item->updatedAt->diffForHumans() }}
                    </time>
                </li>
            @endforeach
        </ul>
    @endif

    <x-slot:footer>
        <div class="text-right">
            <a href="{{ $allUrl }}" class="text-sm font-medium text-link hover:underline">
                {{ $allLabel ?? __('View all') }}
            </a>
        </div>
    </x-slot:footer>
</x-card>
