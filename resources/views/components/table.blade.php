@props(['head' => null, 'foot' => null])

{{-- Scroll sideways, so a phone does not cut off the right-hand columns.
     `relative` keeps absolute children, such as sr-only text, inside the scroller.
     Without it, they widen the whole page. --}}
<div class="relative bg-surface-raised overflow-x-auto shadow-xs sm:rounded-lg">
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
