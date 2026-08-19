@props(['action', 'direction', 'disabled' => false])

<form method="POST" action="{{ $action }}">
    @csrf
    @method('PATCH')
    <x-icon-button
        type="submit"
        variant="ghost"
        :icon="$direction === 'up' ? 'chevron-up' : 'chevron-down'"
        :label="$direction === 'up' ? __('Move up') : __('Move down')"
        :disabled="(bool) $disabled"
        {{ $attributes }}
    />
</form>
