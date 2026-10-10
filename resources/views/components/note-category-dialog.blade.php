{{--
    One shared dialog for "New category" and "Edit category". The tree's buttons fill it
    with an `open-note-category` event: action, method, title, name, parentId, blocked.
--}}
@props(['tree'])

<div
    x-data="{ action: '', method: 'POST', title: '', name: '', parentId: '', blocked: [] }"
    x-on:open-note-category.window="
        action = $event.detail.action;
        method = $event.detail.method;
        title = $event.detail.title;
        name = $event.detail.name;
        parentId = $event.detail.parentId ?? '';
        blocked = $event.detail.blocked;
        $dispatch('open-modal', 'note-category');
    "
>
    <x-dialog name="note-category" :title="__('Category')" title-expression="title" max-width="md">
        <form id="note-category-form" method="POST" x-bind:action="action" class="space-y-4" x-on:open-modal.window="$event.detail === 'note-category' && setTimeout(() => $refs.name.select(), 150)">
            @csrf
            <input type="hidden" name="_method" value="PATCH" x-bind:disabled="method !== 'PATCH'">

            <div>
                <x-input-label for="note-category-name" :value="__('Name')" />
                <x-text-input id="note-category-name" name="name" type="text" class="mt-1 block w-full" x-ref="name" x-model="name" maxlength="255" required />
            </div>

            <div>
                <x-input-label for="note-category-parent" :value="__('Inside')" />
                <x-note-category-select
                    id="note-category-parent"
                    name="parent_id"
                    class="mt-1 block w-full sm:text-sm"
                    :tree="$tree"
                    :empty-label="__('Top level')"
                    blocked="blocked"
                    x-model="parentId"
                />
            </div>
        </form>

        <x-slot name="footer">
            <x-button variant="secondary" type="button" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
            <x-button variant="primary" type="submit" form="note-category-form">{{ __('Save') }}</x-button>
        </x-slot>
    </x-dialog>
</div>
