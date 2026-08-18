@props(['name', 'legend', 'options', 'selected', 'format' => 'ratio', 'hint' => null])

@php
    $tickLabel = function (string $value) use ($format): string {
        $isPercentage = str_ends_with($value, '%');
        $number = (float) rtrim($value, '%');
        $multiplier = $isPercentage ? $number / 100 : $number;

        return match ($format) {
            'px' => round($number / 100 * 16).'px',
            'times' => rtrim(rtrim(number_format($multiplier, 2), '0'), '.').'×',
            default => $value,
        };
    };
@endphp

<fieldset>
    <legend class="text-sm font-medium text-content">
        {{ $legend }}

        @if ($hint)
            <span class="block text-xs font-normal text-content-muted">{{ $hint }}</span>
        @endif
    </legend>

    <div
        class="mt-3 flex touch-none items-stretch"
        x-data="settingTrack"
        x-on:pointerdown.prevent="start($event)"
        x-on:pointermove="move($event)"
        x-on:pointerup="stop($event)"
        x-on:pointercancel="stop($event)"
    >
        @foreach ($options as $slug => $value)
            <label
                for="{{ str_replace('_', '-', $name) }}-{{ $slug }}"
                class="group flex flex-1 cursor-pointer flex-col items-center gap-2"
            >
                <input
                    type="radio"
                    id="{{ str_replace('_', '-', $name) }}-{{ $slug }}"
                    name="{{ $name }}"
                    value="{{ $slug }}"
                    class="peer sr-only"
                    @checked($selected === $slug)
                >

                <span
                    class="rounded-xs px-1 text-xs text-content-muted
                           peer-checked:font-medium peer-checked:text-link
                           peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2
                           peer-focus-visible:outline-focus"
                >
                    {{ $tickLabel($value) }}
                </span>

                <span class="flex h-4 w-full items-center peer-checked:[&>.tick]:hidden peer-checked:[&>.dot]:block">
                    <span @class([
                        'h-0.5 flex-1 rounded-full bg-border-strong',
                        'invisible' => $loop->first,
                    ])></span>

                    <span class="tick h-3 w-0.5 rounded-full bg-border-strong group-hover:h-4 group-hover:bg-link"></span>
                    <span class="dot hidden h-3.5 w-3.5 shrink-0 rounded-full bg-link"></span>

                    <span @class([
                        'h-0.5 flex-1 rounded-full bg-border-strong',
                        'invisible' => $loop->last,
                    ])></span>
                </span>

                <span class="sr-only">{{ __(ucfirst(str_replace('-', ' ', $slug))) }}</span>
            </label>
        @endforeach
    </div>

    <x-input-error class="mt-2" :messages="$errors->get($name)" />
</fieldset>
