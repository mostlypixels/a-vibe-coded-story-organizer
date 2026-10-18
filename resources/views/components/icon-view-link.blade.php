@props(['href', 'label' => null])

<x-icon-button as="a" icon="eye" :label="$label ?? __('View')" href="{{ $href }}" {{ $attributes }} />
