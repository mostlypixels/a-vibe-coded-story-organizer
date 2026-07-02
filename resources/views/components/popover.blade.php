@props([
    'title' => null,
    'position' => 'bottom',
    'width' => 'w-64',
])

@php
    // Click-toggled panel anchored to a trigger. Use the `trigger` slot for the
    // clickable element and the `content` slot for the panel body (like
    // x-dropdown). Closes on outside click or Escape.
    $positions = [
        'top'    => 'bottom-full left-1/2 -translate-x-1/2 mb-2',
        'bottom' => 'top-full left-1/2 -translate-x-1/2 mt-2',
        'left'   => 'right-full top-1/2 -translate-y-1/2 mr-2',
        'right'  => 'left-full top-1/2 -translate-y-1/2 ml-2',
    ][$position];
@endphp

<div
    x-data="{ open: false }"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
    {{ $attributes->merge(['class' => 'relative inline-block']) }}
>
    <div @click="open = ! open">
        {{ $trigger }}
    </div>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute z-50 {{ $positions }} {{ $width }} rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5"
        style="display: none;"
    >
        @if ($title)
            <div class="border-b border-gray-200 px-4 py-2 text-sm font-semibold text-gray-800">
                {{ $title }}
            </div>
        @endif

        <div class="px-4 py-3 text-sm text-gray-600">
            {{ $content }}
        </div>
    </div>
</div>
