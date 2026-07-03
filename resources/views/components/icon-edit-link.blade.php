@props(['href'])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex items-center justify-center p-1.5 rounded-md border border-navy-500 bg-transparent text-navy-500 hover:bg-navy-50']) }} title="{{ __('Edit') }}">
    <span class="sr-only">{{ __('Edit') }}</span>
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793 3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
    </svg>
</a>
