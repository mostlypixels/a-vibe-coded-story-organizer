@props(['modal', 'icon' => 'trash', 'variant' => 'danger', 'label' => null])

{{--
    An icon button that opens a modal instead of submitting.

    Exists for the delete that is too consequential for a native confirm(): a row
    whose children would be destroyed opens the "move or delete" dialog
    (<x-delete-with-move-dialog>) so the writer can reassign them first. The two
    index views that needed it were hand-rolling a <button> with
    <x-icon-delete-button>'s classes copied in, which is how the two drift apart.

    `modal` is the dialog's `name` — the same string its x-modal declares.
--}}
<x-icon-button
    type="button"
    :icon="$icon"
    :variant="$variant"
    :label="$label ?? __('Delete')"
    x-data=""
    x-on:click="$dispatch('open-modal', '{{ $modal }}')"
    {{ $attributes }}
/>
