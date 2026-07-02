{{--
    Non-sortable header cell for x-table. Shares its styling with
    x-sortable-header (the sortable counterpart) — keep the two class strings in
    sync. Render with an empty slot (`<x-table-heading />`) for a spacer column,
    e.g. the trailing row-actions column.
--}}
<th scope="col" {{ $attributes->merge(['class' => 'px-4 py-3 text-left text-xs font-semibold text-navy-900 uppercase tracking-wider']) }}>
    {{ $slot }}
</th>
