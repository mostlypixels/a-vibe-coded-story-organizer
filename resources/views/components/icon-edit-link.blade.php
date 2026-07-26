@props(['href'])

<x-icon-button as="a" icon="pencil" :label="__('Edit')" href="{{ $href }}" {{ $attributes }} />
