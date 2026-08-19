@props(['title' => null, 'open' => true])

<details @if ($open) open @endif {{ $attributes->merge(['class' => 'group bg-surface-raised overflow-hidden shadow-xs sm:rounded-lg']) }}>
    <summary class="flex items-center gap-2 list-none cursor-pointer select-none px-6 py-4 group-open:border-b group-open:border-border [&::-webkit-details-marker]:hidden">
        <x-tabler-chevron-down class="h-4 w-4 shrink-0 text-content-muted transition-transform group-open:rotate-180" />

        <div class="min-w-0 flex-1">
            @isset($header)
                {{ $header }}
            @else
                <x-heading level="3" class="inline">{{ $title }}</x-heading>
            @endisset
        </div>
    </summary>

    <div class="px-6 py-4">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-border bg-surface-sunken px-6 py-4">
            {{ $footer }}
        </div>
    @endisset
</details>
