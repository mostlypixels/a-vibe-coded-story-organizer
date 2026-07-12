@props(['action', 'disabled' => false])

<form method="POST" action="{{ $action }}">
    @csrf
    @method('PATCH')
    <button type="submit" {{ $attributes->merge(['class' => 'inline-flex items-center justify-center p-1.5 rounded-md ' . ($disabled ? 'text-gray-200 cursor-not-allowed' : 'text-gray-400 hover:text-gray-600 hover:bg-gray-100')]) }} title="{{ __('Move down') }}" @disabled($disabled)>
        <span class="sr-only">{{ __('Move down') }}</span>
        <x-tabler-chevron-down class="h-4 w-4" />
    </button>
</form>
