@props(['name', 'selected' => null])

<div x-data="{ selected: @js($selected) }">
    <input type="hidden" name="{{ $name }}" x-model="selected">

    <div class="mt-1 grid grid-cols-8 gap-2">
        @foreach (\App\Support\PlotlineColors::PRESETS as $hex)
            <button
                type="button"
                @click="selected = '{{ $hex }}'"
                :class="{ 'ring-2 ring-offset-2 ring-gray-800': selected === '{{ $hex }}' }"
                class="h-6 w-6 rounded-full"
                style="background-color: {{ $hex }}"
                aria-label="{{ $hex }}"
            ></button>
        @endforeach
    </div>
</div>
