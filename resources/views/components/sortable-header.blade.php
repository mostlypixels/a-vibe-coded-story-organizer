@props(['field', 'sort', 'direction'])

@php
    $isActive = $sort === $field;
    $nextDirection = $isActive && $direction === 'asc' ? 'desc' : 'asc';
    // Sorting reshuffles every row, so the old page number points at different
    // rows afterwards. Drop it and land on page 1, as a filter change does.
    $href = request()->fullUrlWithQuery(['sort' => $field, 'direction' => $nextDirection, 'page' => null]);
@endphp

<th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-table-header-content uppercase tracking-wider">
    <a href="{{ $href }}" class="inline-flex items-center gap-1 hover:underline">
        {{ $slot }}

        @if ($isActive)
            <span class="text-table-header-content">{{ $direction === 'asc' ? '▲' : '▼' }}</span>
        @endif
    </a>
</th>
