@php
    use Illuminate\Support\Str;
@endphp

{{--
    One save point as a single line of text, for a picker's <option>.

    Shared by both selects on the compare page (and, from task 15, by the
    combobox that enhances them) so the two sides can never label the same save
    differently. Plain text only: an <option> can hold nothing else.

    `$number` counts up from the oldest save, so #1 is where the history starts
    and the numbers stay stable as new saves arrive at the top.
--}}
#{{ $number }}
&middot; {{ $point->savedAt->format('d M H:i') }}
@if ($point->label)
    &middot; {{ $point->label }}
@endif
&middot; {{ Str::headline($point->origin->value) }}
@if ($point->isCurrent)
    &middot; {{ __('Current') }}
@endif
