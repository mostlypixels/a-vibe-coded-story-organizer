@props([
    'value',
    'withTime' => false,
    // A guessed date shows "c." and no time.
    'approximate' => false,
])

<span {{ $attributes }}>@if ($approximate){{ __('c.') }} {{ \App\Support\DateFormat::date($value, $locale) }}@else{{ $withTime ? \App\Support\DateFormat::dateTime($value, $locale) : \App\Support\DateFormat::date($value, $locale) }}@endif</span>
