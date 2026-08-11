@props(['name', 'legend', 'options', 'selected', 'hint' => null])

{{--
    One labelled radio group for a slug list out of `config/fonts.php`
    (sizes, line spacing). Native radios, so arrow keys move between the
    options and the live preview hears a `change` event.

    `options` is the config list itself: the keys are the slugs, the values are
    the authored CSS and are never printed.
--}}

<fieldset class="space-y-2">
    <legend class="text-sm font-medium text-content">
        {{ $legend }}

        @if ($hint)
            <span class="block text-xs font-normal text-content-muted">{{ $hint }}</span>
        @endif
    </legend>

    @foreach ($options as $slug => $value)
        <label
            for="{{ str_replace('_', '-', $name) }}-{{ $slug }}"
            class="flex items-center gap-3 rounded-md border border-border px-3 py-2 cursor-pointer"
        >
            <input
                type="radio"
                id="{{ str_replace('_', '-', $name) }}-{{ $slug }}"
                name="{{ $name }}"
                value="{{ $slug }}"
                class="border-border-strong text-link focus:ring-focus"
                @checked($selected === $slug)
            >
            <span class="text-sm text-content">{{ __(ucfirst($slug)) }}</span>
        </label>
    @endforeach

    <x-input-error class="mt-2" :messages="$errors->get($name)" />
</fieldset>
