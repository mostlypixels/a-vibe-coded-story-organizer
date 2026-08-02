@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-success-surface-content']) }}>
        {{ $status }}
    </div>
@endif
