@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'text-sm text-danger-surface-content space-y-1']) }}>
        @foreach (\Illuminate\Support\Arr::flatten((array) $messages) as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
