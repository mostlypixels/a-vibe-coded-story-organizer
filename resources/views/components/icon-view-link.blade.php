@props(['href'])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex items-center justify-center p-1.5 rounded-md border border-navy-500 bg-transparent text-navy-500 hover:bg-navy-50']) }} title="{{ __('View') }}">
    <span class="sr-only">{{ __('View') }}</span>
    <x-tabler-eye class="h-4 w-4" />
</a>
