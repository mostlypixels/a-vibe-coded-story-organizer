@props([
    'colspan' => 1,
    'filtered' => false,
    'createUrl' => null,
    'createLabel' => null,
    'items' => null,
])

<tr>
    <td colspan="{{ $colspan }}" class="px-4 py-10 text-center text-content-muted">
        @if ($filtered)
            {{ __('No :items match your search or filters.', ['items' => $items ?? __('results')]) }}
        @else
            <div class="flex flex-col items-center gap-3">
                <x-tabler-circle-check class="h-10 w-10 text-content-subtle" aria-hidden="true" />
                <p class="font-medium text-content-muted">
                    {{ $slot->isNotEmpty() ? $slot : __('No :items yet.', ['items' => $items ?? __('entries')]) }}
                </p>
                @if ($createUrl && $createLabel)
                    <x-button variant="primary" :href="$createUrl">{{ $createLabel }}</x-button>
                @endif
            </div>
        @endif
    </td>
</tr>
