@props(['action', 'direction', 'disabled' => false])

{{--
    One step of a reorder, as a PATCH form. `direction` is `up` or `down` — the two
    were byte-identical components apart from the glyph and the label.

    `disabled` only needs setting once: the `ghost` variant styles its own disabled
    state, so the greyed-out look follows the attribute. The server is authoritative
    either way — HasSiblingPosition treats a move past either end as a no-op.

    > [!WARNING]
    > `:disabled="…"`, never `@disabled(…)`. A Blade *directive* used as an attribute
    > inside an `<x-…>` tag stops the component-tag compiler matching the tag at all,
    > and the component is then emitted as literal text on the page. A bound boolean
    > is the equivalent that works: the attribute bag drops `false` and renders `true`
    > as `disabled="disabled"`. Guarded by IconButtonComponentTest.
--}}
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
