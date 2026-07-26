@props(['href' => null, 'download' => null])

{{--
    Outline icon-only download link. `href`/`download` are plain Blade props for
    the static case; for an Alpine-driven target (e.g. a live preview pane) pass
    `x-bind:href` / `x-bind:download` instead — being undeclared props they fall
    through to $attributes and land on the <a> tag directly.

    > [!WARNING]
    > The optional attributes are bound values (`:href="…"`), never an `@if` block
    > inside the tag. A Blade *directive* used as an attribute inside an `<x-…>` tag
    > stops the component-tag compiler matching the tag, and the component is then
    > emitted as literal text on the page. The attribute bag already omits a `null`
    > attribute and renders `true` as `download="download"`, which is exactly what
    > the two optional cases need. Guarded by IconButtonComponentTest.
--}}
<x-icon-button
    as="a"
    icon="download"
    :label="__('Download')"
    :href="$href"
    :download="$download === true ? true : ($download ?: null)"
    {{ $attributes }}
/>
