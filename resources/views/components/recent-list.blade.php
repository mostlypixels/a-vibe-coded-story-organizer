@props([
    'title',
    'items',
    'allUrl',
    'allLabel' => null,
    'noun' => null,
    'showCovers' => false,
])

{{--
    A "Recently edited" card for the section landing pages: the last few
    App\Support\RecentItem rows, newest first, then a link to the full index.
    Rows carry the striping and edge-to-edge dividers of the index tables.

    `show-covers` is a property of the ENTITY, not of the data: turn it on for a
    list whose entity has a cover field (codex entries, chapters) and every row
    gets a thumbnail box, empty when no image is saved. Lists with no cover
    field (acts, scenes, events) leave it off and stay flush left.

    Times render twice on purpose — a relative <time> for scanning ("2 hours
    ago") with the exact timestamp in its title for anyone who needs it.
--}}
<x-card :title="$title" stretch flush-footer :padded="false">
    @if (count($items) === 0)
        <p class="px-6 py-4 text-sm text-content-muted">
            {{ __('No :items yet.', ['items' => $noun ?? __('entries')]) }}
        </p>
    @else
        <ul class="divide-y divide-border">
            @foreach ($items as $item)
                <li class="flex items-center gap-3 px-6 py-3 {{ $loop->even ? 'bg-surface' : 'bg-surface-raised' }}">
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
                        @if ($item->context)
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
