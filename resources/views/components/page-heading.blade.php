@props(['level' => 1])

{{--
    The document heading at the top of a page's content column.

    Breadcrumbs (the header band's <nav>) are navigation, not a heading — a
    page still needs its own <h1> for document outline and screen-reader
    "jump to heading" navigation. This wraps x-heading at a fixed level and
    consistent spacing so every converted page's heading looks and behaves
    the same rather than each view improvising its own margin.
--}}
<div class="mb-6">
    <x-heading :level="$level">{{ $slot }}</x-heading>
</div>
