@props([
    'name',
    'value' => null,
    'min' => null,
    'max' => null,
    'required' => false,
    'id' => null,
])

@php
    // Order, month labels and clock come from the shared locale, never a map.
    $fieldId = $id ?? $name;
    $order = \App\Support\DateFormat::segmentOrder($locale);
    $months = \App\Support\DateFormat::monthNames($locale);
    $twelveHour = \App\Support\DateFormat::usesTwelveHourClock($locale);
    $config = [
        'value' => (string) $value,
        'min' => (string) $min,
        'max' => (string) $max,
        'twelveHour' => $twelveHour,
    ];
    // The notice shows where the clamp puts the date: one minute inside the window.
    $minLabel = $min
        ? \App\Support\DateFormat::dateTime(\Illuminate\Support\Carbon::parse($min)->addMinute(), $locale)
        : null;
    $maxLabel = $max
        ? \App\Support\DateFormat::dateTime(\Illuminate\Support\Carbon::parse($max)->subMinute(), $locale)
        : null;
    // Mirror the Alpine init() so the right row shows before Alpine boots.
    $hasTime = preg_match('/T(\d{2}):(\d{2})$/', (string) $value, $time) === 1
        && ! ((int) $time[1] === 0 && (int) $time[2] === 0);
@endphp

<div
    x-data="dateField({{ \Illuminate\Support\Js::from($config) }})"
    data-date-field="{{ $name }}"
    {{ $attributes->merge(['class' => 'mt-1']) }}
>
    <input type="hidden" name="{{ $name }}" x-model="value" value="{{ $value }}" @required($required)>

    <div class="flex flex-wrap items-center gap-2">
        @foreach (str_split($order) as $segment)
            @if ($segment === 'y')
                <div>
                    <label class="sr-only" for="{{ $fieldId }}_year">{{ __('Year') }}</label>
                    <x-text-input
                        :id="$fieldId.'_year'"
                        type="text"
                        inputmode="numeric"
                        class="w-24"
                        data-segment="year"
                        x-model="year"
                        x-on:input="onYearInput($event)"
                        x-on:change="sync()"
                        :placeholder="__('Year')"
                    />
                </div>
            @elseif ($segment === 'm')
                <div>
                    <label class="sr-only" for="{{ $fieldId }}_month">{{ __('Month') }}</label>
                    <x-select
                        :id="$fieldId.'_month'"
                        data-segment="month"
                        x-model="month"
                        x-on:change="clampDay(); sync()"
                    >
                        <option value="">{{ __('Month') }}</option>
                        @foreach ($months as $number => $label)
                            <option value="{{ $number }}">{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>
            @else
                <div>
                    <label class="sr-only" for="{{ $fieldId }}_day">{{ __('Day') }}</label>
                    <x-text-input
                        :id="$fieldId.'_day'"
                        type="text"
                        inputmode="numeric"
                        class="w-16"
                        data-segment="day"
                        x-model="day"
                        x-on:input="onDayInput($event)"
                        x-on:blur="clampDay()"
                        :placeholder="__('Day')"
                    />
                </div>
            @endif
        @endforeach

        <button
            type="button"
            x-show="! showTime"
            style="{{ $hasTime ? 'display: none;' : '' }}"
            x-on:click="addTime()"
            data-date-field-add-time
            class="text-sm text-link hover:text-link-hover"
        >
            {{ __('+ Add time') }}
        </button>

        <div x-show="showTime" style="{{ $hasTime ? '' : 'display: none;' }}" class="flex items-center gap-2" data-date-field-time>
            <label class="sr-only" for="{{ $fieldId }}_hour">{{ __('Hour') }}</label>
            <x-select :id="$fieldId.'_hour'" data-segment="hour" x-model="hour" x-on:change="sync()">
                @foreach ($twelveHour ? range(1, 12) : range(0, 23) as $hour)
                    <option value="{{ $hour }}">{{ $twelveHour ? $hour : str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}</option>
                @endforeach
            </x-select>

            <span aria-hidden="true">:</span>

            <label class="sr-only" for="{{ $fieldId }}_minute">{{ __('Minute') }}</label>
            <x-select :id="$fieldId.'_minute'" data-segment="minute" x-model="minute" x-on:change="sync()">
                @foreach (range(0, 59) as $minute)
                    <option value="{{ str_pad((string) $minute, 2, '0', STR_PAD_LEFT) }}">{{ str_pad((string) $minute, 2, '0', STR_PAD_LEFT) }}</option>
                @endforeach
            </x-select>

            @if ($twelveHour)
                <label class="sr-only" for="{{ $fieldId }}_meridiem">{{ __('AM or PM') }}</label>
                <x-select :id="$fieldId.'_meridiem'" data-segment="meridiem" x-model="meridiem" x-on:change="sync()">
                    <option value="AM">{{ __('AM') }}</option>
                    <option value="PM">{{ __('PM') }}</option>
                </x-select>
            @endif

            <button type="button" x-on:click="clearTime()" class="text-sm text-link hover:text-link-hover">
                {{ __('Remove time') }}
            </button>
        </div>
    </div>

    <p
        x-show="clampedTo !== ''"
        style="display: none;"
        data-date-field-notice
        class="mt-1 text-sm text-content-muted"
    >
        <span x-show="clampedTo === 'min'">
            {{ __('Moved to :datetime, just after the start of your story.', ['datetime' => $minLabel]) }}
        </span>
        <span x-show="clampedTo === 'max'">
            {{ __('Moved to :datetime, just before the end of your story.', ['datetime' => $maxLabel]) }}
        </span>
    </p>
</div>
