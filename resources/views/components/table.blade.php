@props(['head' => null])

{{--
    Card-wrapped data table with striped, optionally sortable rows. Pair it with
    x-sortable-header / x-table-heading (in the `head` slot), x-table-row for the
    body rows, and x-table-empty for the no-results state. In the head slot, use an
    empty x-table-heading as the trailing row-actions column. Sketch:

        x-table
          x-slot:head
            x-sortable-header field="name" ... (sortable column)
            x-table-heading (label column)
            x-table-heading (empty: row-actions column)
          forelse rows
            x-table-row :striped="$loop->even" ... td cells ...
          empty
            x-table-empty :colspan="N" (no-results message)
--}}
<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
    <table {{ $attributes->merge(['class' => 'min-w-full divide-y divide-gray-200']) }}>
        @isset($head)
            <thead class="bg-sun-400">
                <tr>{{ $head }}</tr>
            </thead>
        @endisset

        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
