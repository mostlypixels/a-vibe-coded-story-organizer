@props(['genre', 'checked' => false])

@php
    $id = 'genre-'.$genre->value;
@endphp

<label for="{{ $id }}" class="cursor-pointer">
    <input
        type="radio"
        id="{{ $id }}"
        name="genre"
        value="{{ $genre->value }}"
        class="peer sr-only"
        @checked($checked)
    >

    <span
        class="block h-full rounded-md border border-border p-4
               peer-checked:border-link peer-checked:ring-1 peer-checked:ring-link
               peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2
               peer-focus-visible:outline-focus"
    >
        <span class="block font-semibold text-content">{{ __($genre->label()) }}</span>
        <span class="mt-1 block text-sm text-content-muted">{{ __($genre->description()) }}</span>
    </span>
</label>
