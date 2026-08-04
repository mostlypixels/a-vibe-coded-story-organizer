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
