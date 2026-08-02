@props(['field', 'sort', 'direction'])

@php
    $isActive = $sort === $field;
    $nextDirection = $isActive && $direction === 'asc' ? 'desc' : 'asc';
    $href = request()->fullUrlWithQuery(['sort' => $field, 'direction' => $nextDirection]);
@endphp

{{--
    The header band has exactly one chosen foreground — `table-header-content` —
    so the link cannot darken on hover the way it used to (there is no second
    shade to move to, and inventing one per band is what the flat vocabulary
    exists to avoid). It underlines instead, the same hover affordance x-button's
    `link` variant uses.

    The sort arrow was `/70` on the same token, to read as secondary without a
    colour of its own. It renders at full strength now: alpha put it at 4.11:1 on
    the dark preset and 3.88:1 on Dusk, under the text floor, and the arrow states
    which column is sorted and in which direction — it is the only thing that does,
    so it is not decoration that may fade.
--}}
<th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-table-header-content uppercase tracking-wider">
    <a href="{{ $href }}" class="inline-flex items-center gap-1 hover:underline">
        {{ $slot }}

        @if ($isActive)
            <span class="text-table-header-content">{{ $direction === 'asc' ? '▲' : '▼' }}</span>
        @endif
    </a>
</th>
