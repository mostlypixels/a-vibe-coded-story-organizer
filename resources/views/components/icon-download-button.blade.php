@props(['href' => null, 'download' => null])

{{--
    Outline icon-only download link. `href`/`download` are plain Blade props for
    the static case; for an Alpine-driven target (e.g. a live preview pane) pass
    `x-bind:href` / `x-bind:download` instead — being undeclared props they fall
    through to $attributes and land on the <a> tag directly.
--}}
<a
    @if ($href) href="{{ $href }}" @endif
    @if ($download === true) download
    @elseif ($download) download="{{ $download }}" @endif
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center p-1.5 rounded-md border border-navy-500 bg-transparent text-navy-500 hover:bg-navy-50']) }}
    title="{{ __('Download') }}"
>
    <span class="sr-only">{{ __('Download') }}</span>
    <x-tabler-download class="h-4 w-4" />
</a>
