@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'text-sm text-red-600 space-y-1']) }}>
        {{-- Flatten so a wildcard bag like $errors->get('reference_images.*'),
             which returns messages keyed by their per-item key (e.g. ['reference_images.1' => ['…']]),
             renders each string. A plain flat array is unchanged. --}}
        @foreach (\Illuminate\Support\Arr::flatten((array) $messages) as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
