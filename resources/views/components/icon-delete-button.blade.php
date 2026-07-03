@props(['action', 'confirm'])

<form method="POST" action="{{ $action }}" onsubmit="return confirm('{{ $confirm }}')">
    @csrf
    @method('DELETE')
    <button type="submit" {{ $attributes->merge(['class' => 'inline-flex items-center justify-center p-1.5 rounded-md border border-red-600 bg-transparent text-red-600 hover:bg-red-50']) }} title="{{ __('Delete') }}">
        <span class="sr-only">{{ __('Delete') }}</span>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M8.75 1a.75.75 0 00-.75.75V3H4a.75.75 0 000 1.5h.278l.66 10.276A2.25 2.25 0 007.184 17h5.632a2.25 2.25 0 002.246-2.224L15.722 4.5H16a.75.75 0 000-1.5h-4v-.25A.75.75 0 0011.25 1h-2.5zM10 6a.75.75 0 01.75.75v6.5a.75.75 0 01-1.5 0v-6.5A.75.75 0 0110 6zm-2.75.75a.75.75 0 00-1.5 0v6.5a.75.75 0 001.5 0v-6.5zm5.25-.75a.75.75 0 01.75.75v6.5a.75.75 0 01-1.5 0v-6.5a.75.75 0 01.75-.75z" clip-rule="evenodd" />
        </svg>
    </button>
</form>
