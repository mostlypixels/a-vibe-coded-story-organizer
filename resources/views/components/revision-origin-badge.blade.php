@props(['origin'])

@php
    $variant = match ($origin) {
        \App\Enums\RevisionOrigin::Automatic => 'neutral',
        \App\Enums\RevisionOrigin::Manual => 'info',
        \App\Enums\RevisionOrigin::Revert => 'warning',
        \App\Enums\RevisionOrigin::Baseline => 'neutral',
    };
@endphp

<x-badge :variant="$variant" {{ $attributes }}>{{ $origin->label() }}</x-badge>
