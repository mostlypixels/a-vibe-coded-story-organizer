@props(['colspan' => 1])

{{-- Full-width "no results" row for x-table's @empty branch. --}}
<tr>
    <td colspan="{{ $colspan }}" class="px-4 py-6 text-center text-gray-500">
        {{ $slot }}
    </td>
</tr>
