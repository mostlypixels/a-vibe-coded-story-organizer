{{-- Neutral, not danger: a scene with no event is normal, not an error. --}}
<span title="{{ __('This scene has no “happens during” event yet.') }}" {{ $attributes->merge(['class' => 'inline-flex items-center rounded-md border border-border px-2 py-0.5 text-xs font-medium text-content-muted whitespace-nowrap']) }}>{{ __('No event') }}</span>
