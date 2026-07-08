@props([
    'colspan' => 1,
    'filtered' => false,
    'createUrl' => null,
    'createLabel' => null,
    'items' => null,
])

{{--
    Full-width empty-state row for x-table's @empty branch. It renders one of two
    messages, chosen by $filtered, so an empty table never reads as a bare
    "no results" line:

      • $filtered = true  → the collection is hidden by an active search/filter.
        Shows a "nothing matches" line; the toolbar's own Clear link is the way back,
        so no call-to-action is offered here.
      • $filtered = false → the collection is genuinely empty. Shows friendly copy and,
        when $createUrl is given, a primary button pointing at the create action.

    $items is the already-translated plural noun for the copy (e.g. __('events')).
    $createLabel is the already-translated button text (e.g. __('New Event')). Pass a
    slot to override the default empty headline.
--}}
<tr>
    <td colspan="{{ $colspan }}" class="px-4 py-10 text-center text-gray-500">
        @if ($filtered)
            {{ __('No :items match your search or filters.', ['items' => $items ?? __('results')]) }}
        @else
            <div class="flex flex-col items-center gap-3">
                <svg class="h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <p class="font-medium text-gray-600">
                    {{ $slot->isNotEmpty() ? $slot : __('No :items yet.', ['items' => $items ?? __('entries')]) }}
                </p>
                @if ($createUrl && $createLabel)
                    <x-button variant="primary" :href="$createUrl">{{ $createLabel }}</x-button>
                @endif
            </div>
        @endif
    </td>
</tr>
