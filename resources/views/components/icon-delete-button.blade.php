@props(['action', 'confirm'])

{{--
    Delete as a real DELETE form, gated by a native confirm(). For a delete that
    needs the richer "move or delete" dialog instead, see <x-icon-dialog-button>.
--}}
<form method="POST" action="{{ $action }}" onsubmit="return confirm('{{ $confirm }}')">
    @csrf
    @method('DELETE')
    <x-icon-button type="submit" icon="trash" variant="danger" :label="__('Delete')" {{ $attributes }} />
</form>
