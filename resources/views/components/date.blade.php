@props([
    'value',
    'withTime' => false,
])

<span {{ $attributes }}>{{ $withTime ? \App\Support\DateFormat::dateTime($value, $locale) : \App\Support\DateFormat::date($value, $locale) }}</span>
