@props(['head' => null, 'foot' => null])

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
          x-slot:foot                        (optional footer band)
            td colspan="N" ... a full-width footer cell (e.g. a "see all" link)

    The optional `foot` slot renders a <tfoot> band under the body — one <tr> the
    caller fills with its own <td>s (spanning the columns as needed). Use it for a
    per-table action such as a "see more" link, not for data rows.
--}}
<div class="bg-surface-raised overflow-hidden shadow-xs sm:rounded-lg">
    <table {{ $attributes->merge(['class' => 'min-w-full divide-y divide-border']) }}>
        @isset($head)
            {{-- `table-header`, not `highlight`: this band is on every table in the
                 app, whereas `highlight` is the search <mark> alone. --}}
            <thead class="bg-table-header">
                <tr>{{ $head }}</tr>
            </thead>
        @endisset

        <tbody>
            {{ $slot }}
        </tbody>

        @isset($foot)
            <tfoot class="bg-table-header border-t border-border">
                <tr>{{ $foot }}</tr>
            </tfoot>
        @endisset
    </table>
</div>
