{{--
    The list version of `x-delete-with-move-dialog`: one copy per page, not one per row.
    Each row's `x-icon-delete-with-move-button` fills it on open. The row itself leaves
    the destination list, so the list renders only once.
--}}
@props([
    'name',
    'title',
    'destinationNoun',
    'destinations',
    'destinationField' => 'move_children_to',
])

@php
    // The row's count phrase replaces `:children` in the browser.
    $texts = [
        'move' => __('Move :children to another :destination, then delete', ['children' => ':children', 'destination' => $destinationNoun]),
        'deleteAll' => __('Delete everything (:cascade)', ['cascade' => ':children']),
        'deleteOnly' => __('This will also delete :cascade.', ['cascade' => ':children']),
    ];
@endphp

<div
    x-data="{
        ids: @js($destinations->pluck('id')),
        texts: @js($texts),
        action: '',
        exclude: null,
        children: '',
        mode: 'move',
        destination: null,
        text(key) { return this.texts[key].replace(':children', this.children) },
        hasDestinations() { return this.ids.some(id => id !== this.exclude) },
    }"
    x-on:open-delete-with-move.window="if ($event.detail.dialog === @js($name)) {
        action = $event.detail.action;
        exclude = $event.detail.exclude;
        children = $event.detail.children;
        mode = 'move';
        destination = ids.find(id => id !== exclude) ?? null;
        $dispatch('open-modal', @js($name));
    }"
>
    <x-dialog :name="$name" :title="$title">
        <form id="{{ $name }}-form" method="POST" x-bind:action="action" class="space-y-4">
            @csrf
            @method('DELETE')

            <div x-show="hasDestinations()" class="space-y-4">
                <label class="flex items-start gap-2">
                    <input type="radio" name="delete_mode" value="move" x-model="mode" class="mt-1">
                    <span class="text-sm text-content-muted" x-text="text('move')"></span>
                </label>

                <div x-show="mode === 'move'" class="pl-6">
                    <x-input-label for="{{ $name }}-destination" :value="__('Destination')" class="sr-only" />
                    <x-select
                        id="{{ $name }}-destination"
                        name="{{ $destinationField }}"
                        x-model.number="destination"
                        x-bind:required="hasDestinations() && mode === 'move'"
                        x-bind:disabled="! hasDestinations() || mode !== 'move'"
                        class="mt-1 block w-full sm:text-sm"
                    >
                        @foreach ($destinations as $destination)
                            <option value="{{ $destination->id }}" x-bind:hidden="exclude === {{ $destination->id }}" x-bind:disabled="exclude === {{ $destination->id }}">{{ $destination->name }}</option>
                        @endforeach
                    </x-select>
                </div>

                <label class="flex items-start gap-2">
                    <input type="radio" name="delete_mode" value="delete" x-model="mode" class="mt-1">
                    <span class="text-sm text-content-muted" x-text="text('deleteAll')"></span>
                </label>
            </div>

            <p x-show="! hasDestinations()" class="text-sm text-content-muted" x-text="text('deleteOnly')"></p>

            <x-slot name="footer">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
                <x-button variant="danger" type="submit" form="{{ $name }}-form">{{ __('Confirm') }}</x-button>
            </x-slot>
        </form>
    </x-dialog>
</div>
