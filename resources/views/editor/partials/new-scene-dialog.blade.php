{{-- The "New scene" dialog of the Write page. Alpine posts it, so it has no <form>.
     It sits outside the side panel, because a collapsed panel hides its content. --}}

{{-- Keep x-data on this wrapper because x-dialog does not forward attributes to x-modal. --}}
<div
    x-data="editorNewScene({
        url: @js(route('books.scenes.quick-store', $book)),
        chapterId: @js($currentChapterId),
        nameSelector: '#editor-new-scene-name',
        invalidMessage: @js(__('That scene could not be created.')),
        failureMessage: @js(__('The scene was not created. Check your connection and try again.')),
    })"
>
    <x-dialog name="editor-new-scene" :title="__('New scene')" max-width="md">
        <div class="space-y-4">
            <div>
                <x-input-label for="editor-new-scene-chapter" :value="__('Chapter')" />
                <x-select id="editor-new-scene-chapter" class="mt-1 block w-full" x-model="chapterId" required>
                    @foreach ($chapters as $chapter)
                        <option value="{{ $chapter->id }}">{{ $chapter->act->name }} &mdash; {{ $chapter->name }}</option>
                    @endforeach
                </x-select>
            </div>

            <div>
                <x-input-label for="editor-new-scene-name" :value="__('Title')" />
                <x-text-input
                    id="editor-new-scene-name"
                    type="text"
                    class="mt-1 block w-full"
                    x-model="name"
                    x-on:keydown.enter.prevent="submit()"
                    :placeholder="__('e.g. A Lady at the Fountain')"
                    required
                />
            </div>

            <p class="text-sm text-danger" x-show="error" style="display: none;" role="alert" x-text="error" data-editor-new-scene-error></p>
        </div>

        <x-slot name="footer">
            <x-button variant="secondary" type="button" x-on:click="close()">{{ __('Cancel') }}</x-button>
            <x-button variant="primary" type="button" x-on:click="submit()" x-bind:disabled="busy">{{ __('Create') }}</x-button>
        </x-slot>
    </x-dialog>
</div>
