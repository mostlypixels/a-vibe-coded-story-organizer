@props(['title'])

{{--
    One search-results section (Timeline / Story / Codex): an <h2> heading over
    the section's result tables, stacked full-width (one per entity type, like
    the entity list pages — columns proved too narrow for comfortable reading).
    Tables go in the slot; the parent view never renders a section whose tables
    are all empty.
--}}
<section class="space-y-4">
    <x-heading level="2">{{ $title }}</x-heading>

    <div class="space-y-4">
        {{ $slot }}
    </div>
</section>
