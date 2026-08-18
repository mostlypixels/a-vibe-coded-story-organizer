@props([
    'command' => null,
    'args' => null,
    'action' => null,
    'active' => null,
    'activeExpression' => null,
    'label' => null,
    'title',
    'dropdown' => false,
])

@php
    $clickExpression = $action ?? ($command
        ? 'cmd('.Illuminate\Support\Js::from($command)
            .($args ? ', '.Illuminate\Support\Js::from($args) : '').')'
        : null);

    $isActiveExpression = $activeExpression ?? ($active
        ? 'isOn('.Illuminate\Support\Js::from($active[0])
            .(isset($active[1]) ? ', '.Illuminate\Support\Js::from($active[1]) : '').')'
        : null);

    $classes = 'inline-flex min-w-8 items-center justify-center whitespace-nowrap rounded-sm px-2 py-1 text-sm font-medium'
        .($isActiveExpression ? '' : ' text-content-muted hover:bg-neutral');

    // Merge conditional attributes here to keep one valid class attribute.
    $buttonAttributes = $attributes->merge(['class' => $classes]);

    if ($clickExpression) {
        $buttonAttributes = $buttonAttributes->merge(['@click' => $clickExpression]);
    }

    if ($isActiveExpression) {
        $buttonAttributes = $buttonAttributes->merge([
            ':class' => new \Illuminate\Support\HtmlString(
                '('.e($isActiveExpression).") ? 'bg-accent-surface text-accent-content' : 'text-content-muted hover:bg-neutral'"
            ),
        ]);
    }
@endphp

<button type="button" {{ $buttonAttributes }} title="{{ $title }}" aria-label="{{ $title }}">@if ($dropdown)
    <span class="inline-flex items-center gap-0.5">
        {!! $label ?? $slot !!}
        <x-tabler-chevron-down class="h-3 w-3 shrink-0" />
    </span>
@else
    {!! $label ?? $slot !!}
@endif</button>
