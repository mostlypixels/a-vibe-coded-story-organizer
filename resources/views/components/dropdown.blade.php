<div class="relative"
        x-data="dropdown({ id: @js($disclosureId), hover: @js($hover) })"
        @click.outside="close()"
        @close.stop="close()"
        @keydown.escape="escape($event)"
        @keydown.escape.window="escapeOutside()"
        @dropdown-opened.window="otherOpened($event)"
        @if ($hover)
            data-hover
            @pointerenter="pointerEnter($event)"
            @pointerleave="pointerLeave($event)"
        @endif
>
    <div @click="triggerClick($event)">
        {{ $trigger }}
    </div>

    <div x-show="open"
            id="{{ $disclosureId }}"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute z-50 {{ $offsetClasses }} {{ $widthClass }} rounded-md shadow-lg {{ $alignmentClasses }}"
            style="display: none;"
            @if ($closeOnClick)
                @click="close()"
            @endif
    >
        <div class="rounded-md ring-1 ring-black/5 {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
