@props(['messages'])

{{-- `danger-surface-content`, not `danger`: this is body text on the page's own
     surface, and `danger` is the value chosen to carry white on a solid fill —
     as text it reads 4.44:1 on `surface`, under the floor. --}}
@if ($messages)
    <ul {{ $attributes->merge(['class' => 'text-sm text-danger-surface-content space-y-1']) }}>
        {{-- Flatten so a wildcard bag like $errors->get('reference_images.*'),
             which returns messages keyed by their per-item key (e.g. ['reference_images.1' => ['…']]),
             renders each string. A plain flat array is unchanged. --}}
        @foreach (\Illuminate\Support\Arr::flatten((array) $messages) as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
