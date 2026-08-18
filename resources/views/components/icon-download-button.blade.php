@props(['href' => null, 'download' => null])

<x-icon-button
    as="a"
    icon="download"
    :label="__('Download')"
    :href="$href"
    :download="$download === true ? true : ($download ?: null)"
    {{ $attributes }}
/>
