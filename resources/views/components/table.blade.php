@props(['head' => null, 'foot' => null])

<div class="bg-surface-raised overflow-hidden shadow-xs sm:rounded-lg">
    <table {{ $attributes->merge(['class' => 'min-w-full divide-y divide-border']) }}>
        @isset($head)
            <thead class="bg-table-header">
                <tr>{{ $head }}</tr>
            </thead>
        @endisset

        <tbody>
            {{ $slot }}
        </tbody>

        @isset($foot)
            {{-- The slot supplies its own `<tr>` rows: a footer can carry more
                 than one, such as a page total above a full total. --}}
            <tfoot class="bg-table-header border-t border-border">
                {{ $foot }}
            </tfoot>
        @endisset
    </table>
</div>
