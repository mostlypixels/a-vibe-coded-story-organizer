@props(['action', 'disabled' => false])

<form method="POST" action="{{ $action }}">
    @csrf
    @method('PATCH')
    <button type="submit" {{ $attributes->merge(['class' => 'inline-flex items-center justify-center p-1.5 rounded-md ' . ($disabled ? 'text-gray-200 cursor-not-allowed' : 'text-gray-400 hover:text-gray-600 hover:bg-gray-100')]) }} title="{{ __('Move down') }}" @disabled($disabled)>
        <span class="sr-only">{{ __('Move down') }}</span>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 17a.75.75 0 01-.55-.24l-4.25-4.5a.75.75 0 111.1-1.02l3.45 3.148V4.75a.75.75 0 011.5 0v10.638l3.45-3.148a.75.75 0 111.1 1.02l-4.25 4.5A.75.75 0 0110 17z" clip-rule="evenodd" />
        </svg>
    </button>
</form>
