@props(['action', 'confirm'])

<form method="POST" action="{{ $action }}" onsubmit="return confirm('{{ $confirm }}')">
    @csrf
    @method('DELETE')
    <button type="submit" {{ $attributes->merge(['class' => 'inline-flex items-center justify-center p-1.5 rounded-md border border-red-600 bg-transparent text-red-600 hover:bg-red-50']) }} title="{{ __('Delete') }}">
        <span class="sr-only">{{ __('Delete') }}</span>
        <x-tabler-trash class="h-4 w-4" />
    </button>
</form>
