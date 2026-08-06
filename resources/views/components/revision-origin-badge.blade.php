@props(['origin'])

{{--
    Small pill for a Revision's origin (expanded/ui.md "History page"'s
    "origin badge" column). Reuses x-badge's variant palette rather than
    inventing new colors per CLAUDE.md's Tailwind guidance.
--}}
@php
    $variant = match ($origin) {
        \App\Enums\RevisionOrigin::Automatic => 'neutral',
        \App\Enums\RevisionOrigin::Manual => 'info',
        \App\Enums\RevisionOrigin::Revert => 'warning',
        \App\Enums\RevisionOrigin::Import => 'accent',
        \App\Enums\RevisionOrigin::Baseline => 'neutral',
    };
@endphp

<x-badge :variant="$variant" {{ $attributes }}>{{ $origin->label() }}</x-badge>
