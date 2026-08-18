@props(['action', 'confirm', 'buttonClass' => null])

<form method="POST" action="{{ $action }}" onsubmit="return confirm('{{ $confirm }}')" {{ $attributes }}>
    @csrf
    @method('DELETE')
    <x-button variant="danger" :icon="true" :class="$buttonClass">{{ $slot }}</x-button>
</form>
