@props([
    'variant' => 'info',
    'title' => null,
    'dismissible' => false,
])

@php
    // Contextual feedback banner. Each variant carries its container colours,
    // icon tint, and a Tabler icon name. Full class strings keep Tailwind's
    // purge happy.
    //
    // Four tokens per status, and each does one job: `<status>-surface` is the
    // tinted panel, `<status>-surface-content` the text *and the icon* on it, and
    // `<status>` itself the solid fill — kept here only for the border.
    //
    // The icon used to be `<status>`, which reads as the status colour but is the
    // value chosen to carry white: on its own tint it measures 4.47/4.78/4.82 for
    // danger/success/info and **1.85 for warning** (1.34 on Dusk) — an invisible
    // glyph on the one variant that most needs to be noticed. A glyph is not
    // decoration here; it is what distinguishes the four variants for a reader who
    // does not parse the tint. So the icon takes the same foreground the text does.
    // The border stays `<status>`: it carries no information the icon and tint do
    // not already, and borders are exempt from the floors by design.
    $variants = [
        'info' => [
            'container' => 'bg-info-surface text-info-surface-content border-info',
            'icon' => 'text-info-surface-content',
            'name' => 'tabler-info-circle',
        ],
        'success' => [
            'container' => 'bg-success-surface text-success-surface-content border-success',
            'icon' => 'text-success-surface-content',
            'name' => 'tabler-circle-check',
        ],
        'warning' => [
            'container' => 'bg-warning-surface text-warning-surface-content border-warning',
            'icon' => 'text-warning-surface-content',
            'name' => 'tabler-alert-triangle',
        ],
        'danger' => [
            'container' => 'bg-danger-surface text-danger-surface-content border-danger',
            'icon' => 'text-danger-surface-content',
            'name' => 'tabler-circle-x',
        ],
    ][$variant];
@endphp

<div
    x-data="{ show: true }"
    x-show="show"
    role="alert"
    {{ $attributes->merge(['class' => "flex items-start gap-3 rounded-md border px-4 py-3 {$variants['container']}"]) }}
>
    <x-dynamic-component :component="$variants['name']" class="h-5 w-5 shrink-0 {{ $variants['icon'] }}" aria-hidden="true" />

    <div class="flex-1 text-sm">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div @class(['mt-1' => $title])>{{ $slot }}</div>
    </div>

    @if ($dismissible)
        <button type="button" @click="show = false" class="-mr-1 shrink-0 rounded-md p-1 opacity-70 hover:opacity-100 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-current" title="{{ __('Dismiss') }}">
            <span class="sr-only">{{ __('Dismiss') }}</span>
            <x-tabler-x class="h-4 w-4" aria-hidden="true" />
        </button>
    @endif
</div>
