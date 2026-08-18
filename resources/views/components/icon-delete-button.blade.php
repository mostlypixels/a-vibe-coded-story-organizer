@props(['action', 'confirm', 'label' => null])

<form class="flex" method="POST" action="{{ $action }}" onsubmit="return confirm('{{ $confirm }}')">
    @csrf
    @method('DELETE')
    <x-icon-button type="submit" icon="trash" variant="danger" :label="$label ?? __('Delete')" {{ $attributes }} />
</form>
