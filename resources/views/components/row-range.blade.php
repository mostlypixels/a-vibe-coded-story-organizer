@props(['paginator'])

{{--
    The only row range on any paginated page. The published paginator view drops
    the one Laravel ships, which printed nothing on a single-page list and hid
    itself below the `sm` breakpoint.
--}}
<p {{ $attributes->merge(['class' => 'text-sm text-content-muted']) }}>
    {{ __('Showing :first-:last of :total', [
        'first' => $paginator->firstItem() ?? 0,
        'last' => $paginator->lastItem() ?? 0,
        'total' => $paginator->total(),
    ]) }}
</p>
