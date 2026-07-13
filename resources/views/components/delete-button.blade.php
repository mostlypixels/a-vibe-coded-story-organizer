@props(['action', 'confirm'])

{{--
    Labelled delete-with-confirm form: the full-size sibling of x-icon-delete-button.
    Used on entity edit pages (Save/Delete at the bottom of a form) rather than in index
    table rows, where the icon-only version is used instead.
--}}
<form method="POST" action="{{ $action }}" onsubmit="return confirm('{{ $confirm }}')" {{ $attributes }}>
    @csrf
    @method('DELETE')
    <x-button variant="danger" :icon="true">{{ $slot }}</x-button>
</form>
