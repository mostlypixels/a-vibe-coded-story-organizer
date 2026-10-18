{{--
    One side panel of the Write page, inside `editorLayout`. A collapsed panel is a 40 px rail.
    Below `lg`, an open panel is a drawer over the prose. `data-collapsed` and `data-drawer` drive the CSS.
--}}
@props(['side', 'label', 'collapsed' => false, 'width'])

@php
    $bodyId = 'editor-panel-'.$side;
    $hidden = 'group-data-[collapsed=true]/panel:hidden max-lg:group-data-[drawer=closed]/panel:hidden';
    $expandLabel = __('Expand :panel', ['panel' => $label]);
    $collapseLabel = __('Collapse :panel', ['panel' => $label]);
    $edge = $side === 'left' ? 'border-r-4' : 'border-l-4';
    $drawerEdge = $side === 'left' ? 'max-lg:data-[drawer=open]:left-0' : 'max-lg:data-[drawer=open]:right-0';
@endphp

<aside
    {{ $attributes->merge(['class' => "group/panel relative flex min-h-0 flex-col overflow-hidden bg-surface-raised border-border $edge max-lg:data-[drawer=open]:absolute max-lg:data-[drawer=open]:inset-y-0 max-lg:data-[drawer=open]:z-30 max-lg:data-[drawer=open]:w-[min(22rem,85vw)] max-lg:data-[drawer=open]:shadow-lg $drawerEdge"]) }}
    aria-label="{{ $label }}"
    data-editor-panel="{{ $side }}"
    data-collapsed="{{ $collapsed ? 'true' : 'false' }}"
    data-drawer="closed"
    x-bind:data-collapsed="(! isOpen('{{ $side }}')).toString()"
    x-bind:data-drawer="drawer === '{{ $side }}' ? 'open' : 'closed'"
>
    <div class="flex shrink-0 items-center justify-between gap-2 border-b border-border px-1.5 py-1.5 group-data-[collapsed=false]/panel:px-3">
        <h2 class="{{ $hidden }} truncate ps-1.5 text-sm font-semibold text-content">{{ $label }}</h2>

        {{-- Not `x-icon-button`: the icon and the label change with the state. --}}
        <button
            type="button"
            class="inline-flex items-center justify-center rounded-md p-1.5 text-content-subtle hover:bg-neutral hover:text-content-muted"
            title="{{ $collapsed ? $expandLabel : $collapseLabel }}"
            aria-expanded="{{ $collapsed ? 'false' : 'true' }}"
            aria-controls="{{ $bodyId }}"
            data-editor-collapse="{{ $side }}"
            x-bind:title="isOpen('{{ $side }}') ? @js($collapseLabel) : @js($expandLabel)"
            x-bind:aria-expanded="isOpen('{{ $side }}').toString()"
            x-on:click="toggle('{{ $side }}')"
        >
            <span class="sr-only" x-text="isOpen('{{ $side }}') ? @js($collapseLabel) : @js($expandLabel)">{{ $collapsed ? $expandLabel : $collapseLabel }}</span>
            <x-dynamic-component :component="'tabler-layout-sidebar-'.$side.'-expand'" class="hidden h-4 w-4 group-data-[collapsed=true]/panel:block" aria-hidden="true" />
            <x-dynamic-component :component="'tabler-layout-sidebar-'.$side.'-collapse'" class="h-4 w-4 group-data-[collapsed=true]/panel:hidden" aria-hidden="true" />
        </button>
    </div>

    <div id="{{ $bodyId }}" class="{{ $hidden }} min-h-0 flex-1 overflow-y-auto px-5 py-3">
        {{ $slot }}
    </div>

    {{-- Widths apply from `lg` up only, so the handle shows there only. --}}
    <div
        role="separator"
        aria-orientation="vertical"
        tabindex="0"
        aria-label="{{ $side === 'left' ? __('Resize left panel') : __('Resize right panel') }}"
        aria-valuemin="{{ config('editor.min_width') }}"
        aria-valuemax="{{ config('editor.max_width') }}"
        aria-valuenow="{{ $width }}"
        x-bind:aria-valuemax="maxWidth()"
        x-bind:aria-valuenow="{{ $side }}.width"
        class="absolute inset-y-0 {{ $side === 'left' ? '-right-0.5' : '-left-0.5' }} z-10 w-1.5 cursor-col-resize touch-none hover:bg-accent/40 focus-visible:bg-accent focus-visible:outline-none max-lg:hidden group-data-[collapsed=true]/panel:hidden"
        data-editor-resize="{{ $side }}"
        x-on:pointerdown="startDrag('{{ $side }}', $event)"
        x-on:pointermove="moveDrag($event)"
        x-on:pointerup="endDrag($event)"
        x-on:pointercancel="endDrag($event)"
        x-on:keydown="onHandleKey('{{ $side }}', $event)"
        x-on:dblclick="resetWidth('{{ $side }}')"
    ></div>
</aside>
