@aware(['disclosureId' => null])

{{-- Put this button in the trigger slot of x-dropdown or x-popover. It then names the panel it opens. --}}
<button
    @if ($disclosureId)
        aria-expanded="false"
        :aria-expanded="open.toString()"
        aria-controls="{{ $disclosureId }}"
    @endif
    {{ $attributes->merge(['type' => 'button']) }}
>{{ $slot }}</button>
