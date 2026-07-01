@props(['action', 'disabled' => false])

<form method="POST" action="{{ $action }}">
    @csrf
    @method('PATCH')
    <button type="submit" {{ $attributes->merge(['class' => 'inline-flex items-center justify-center p-1.5 rounded-md ' . ($disabled ? 'text-gray-200 cursor-not-allowed' : 'text-gray-400 hover:text-gray-600 hover:bg-gray-100')]) }} title="{{ __('Move up') }}" @disabled($disabled)>
        <span class="sr-only">{{ __('Move up') }}</span>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 3a.75.75 0 01.55.24l4.25 4.5a.75.75 0 11-1.1 1.02L10.75 5.612V16.25a.75.75 0 01-1.5 0V5.612L6.3 8.76a.75.75 0 11-1.1-1.02l4.25-4.5A.75.75 0 0110 3z" clip-rule="evenodd" />
        </svg>
    </button>
</form>
