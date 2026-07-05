@props(['html' => null, 'limit' => 120])

{{--
    A short, PLAIN-TEXT preview of a rich-HTML field for index/list table cells.
    Strips all tags and truncates, then renders escaped ({{ }}) — never {!! !!} —
    so no markup (safe or otherwise) leaks into the striped x-table rows and the
    layout stays intact. Full rich rendering only happens on detail pages via
    x-rich-text.
--}}
{{ Str::of($html ?? '')->stripTags()->squish()->limit($limit) }}
