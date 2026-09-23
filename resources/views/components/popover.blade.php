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
        id="{{ $disclosureId }}"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute z-50 {{ $positionClasses }} {{ $width }} rounded-lg bg-surface-overlay shadow-lg ring-1 ring-black/5"
        style="display: none;"
    >
        @if ($title)
            <div class="border-b border-border px-4 py-2 text-sm font-semibold text-content">
                {{ $title }}
            </div>
        @endif

        <div class="px-4 py-3 text-sm text-content-muted">
            {{ $content }}
        </div>
    </div>
</div>
