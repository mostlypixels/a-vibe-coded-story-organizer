@props(['href'])

<x-icon-button as="a" icon="eye" :label="__('View')" href="{{ $href }}" {{ $attributes }} />
