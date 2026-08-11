@props(['name', 'legend', 'options', 'selected', 'format' => 'ratio', 'hint' => null])

@php
    /**
     * A radio group laid out as a tick track: one tick per step, the active one
     * marked and its label highlighted.
     *
     * It only looks like a slider. The controls are the same native radios the
     * rest of this form uses, so arrow keys work, the form submits without
     * JavaScript, and what posts is a config slug — never a numeric index that
     * would make the order of `config/fonts.php` part of the wire format.
     *
     * Tick labels are derived from the authored value, so there is no second
     * list of numbers to drift out of step:
     *   `px`    — a percentage against the 16px root ("14px")
     *   `times` — a multiplier ("1.15×"), written either as a percentage
     *             (manuscript scale) or as a bare number (line spacing)
     *   `ratio` — the value as authored
     */
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

    {{-- Dragging is enhancement: the handlers only move the checked radio, so
         with JS off the labels still click and the arrow keys still work. --}}
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

                {{-- The radio is visually hidden, so focus has to show somewhere:
                     it rings the step's own label. --}}
                <span
                    class="rounded-xs px-1 text-xs text-content-muted
                           peer-checked:font-medium peer-checked:text-link
                           peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2
                           peer-focus-visible:outline-focus"
                >
                    {{ $tickLabel($value) }}
                </span>

                {{-- The rail is drawn per step rather than as one line behind the
                     ticks, so it needs no absolute positioning and no width
                     maths: each step owns its own segment. The first and last
                     segments stop at the tick so the rail does not overhang. --}}
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
