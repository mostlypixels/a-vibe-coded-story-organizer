@props(['field', 'sort', 'direction'])

@php
    $isActive = $sort === $field;
    $nextDirection = $isActive && $direction === 'asc' ? 'desc' : 'asc';
    $href = request()->fullUrlWithQuery(['sort' => $field, 'direction' => $nextDirection]);
@endphp

<th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-navy-900 uppercase tracking-wider">
    <a href="{{ $href }}" class="inline-flex items-center gap-1 hover:text-navy-950">
        {{ $slot }}

        @if ($isActive)
            <span class="text-navy-700">{{ $direction === 'asc' ? '▲' : '▼' }}</span>
        @endif
    </a>
</th>
