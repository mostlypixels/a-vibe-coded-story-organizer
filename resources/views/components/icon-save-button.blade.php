@props(['type' => 'submit'])

<button {{ $attributes->merge(['type' => $type, 'class' => 'inline-flex items-center justify-center p-1.5 rounded-md border border-navy-500 bg-transparent text-navy-500 hover:bg-navy-50 focus:outline-none focus:ring-2 focus:ring-ocean-500 focus:ring-offset-2 transition ease-in-out duration-150']) }} title="{{ __('Save') }}">
    <span class="sr-only">{{ __('Save') }}</span>
    <x-tabler-device-floppy class="h-4 w-4" />
</button>
