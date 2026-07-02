@props([
    'variant' => 'info',
    'title' => null,
    'dismissible' => false,
])

@php
    // Contextual feedback banner. Each variant carries its container colours,
    // icon tint, and an inline heroicon path. Full class strings keep Tailwind's
    // purge happy.
    $variants = [
        'info' => [
            'container' => 'bg-blue-50 text-blue-800 border-blue-200',
            'icon' => 'text-blue-400',
            'path' => 'M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z',
        ],
        'success' => [
            'container' => 'bg-green-50 text-green-800 border-green-200',
            'icon' => 'text-green-400',
            'path' => 'M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z',
        ],
        'warning' => [
            'container' => 'bg-yellow-50 text-yellow-800 border-yellow-200',
            'icon' => 'text-yellow-400',
            'path' => 'M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z',
        ],
        'danger' => [
            'container' => 'bg-red-50 text-red-800 border-red-200',
            'icon' => 'text-red-400',
            'path' => 'M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z',
        ],
    ][$variant];
@endphp

<div
    x-data="{ show: true }"
    x-show="show"
    role="alert"
    {{ $attributes->merge(['class' => "flex items-start gap-3 rounded-md border px-4 py-3 {$variants['container']}"]) }}
>
    <svg class="h-5 w-5 flex-shrink-0 {{ $variants['icon'] }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="{{ $variants['path'] }}" clip-rule="evenodd" />
    </svg>

    <div class="flex-1 text-sm">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div @class(['mt-1' => $title])>{{ $slot }}</div>
    </div>

    @if ($dismissible)
        <button type="button" @click="show = false" class="-mr-1 flex-shrink-0 rounded-md p-1 opacity-70 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-current" title="{{ __('Dismiss') }}">
            <span class="sr-only">{{ __('Dismiss') }}</span>
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
            </svg>
        </button>
    @endif
</div>
