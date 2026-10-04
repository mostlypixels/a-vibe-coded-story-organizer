@props(['icon'])

{{-- A large faint copy of the icon tells card types apart at a glance. The parent needs `relative isolate overflow-hidden`. --}}
<x-dynamic-component :component="$icon" class="pointer-events-none absolute top-1/2 -left-4 -z-10 h-20 w-20 -translate-y-1/2 -rotate-[20deg] text-content-muted opacity-15" aria-hidden="true" />
