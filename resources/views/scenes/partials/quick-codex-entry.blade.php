{{-- The "New codex entry" dialog. It must stay outside the scene <form>: it is posted by
     Alpine, and a stray Enter here must never submit the scene. --}}
@php
    use App\Enums\CodexEntryType;
@endphp

{{-- Keep x-data on this wrapper because x-dialog does not forward attributes to x-modal. --}}
<div
    x-data="quickCodexEntry({
        url: @js(route('scenes.codex-entries.store', $scene)),
        autosaveKey: @js('scene:'.$scene->id.':contents'),
        editorSelector: @js('[data-autosave-field=\'scene:'.$scene->id.':contents\'] .ProseMirror'),
        nameSelector: '#quick-codex-entry-name',
        listSelector: '[data-codex-references-list]',
        confirmationSelector: '[data-codex-entry-created]',
        entryUrlTemplate: @js(route('codex.show', ['codexEntry' => '__ID__'])),
        defaultType: @js(CodexEntryType::Character->value),
        successMessage: @js(__('Added :name to the codex.')),
        invalidMessage: @js(__('That entry could not be created.')),
        failureMessage: @js(__('The entry was not created. Check your connection and try again.')),
    })"
    x-on:close.capture="restoreEditorFocus()"
    x-on:keydown.escape.window="restoreEditorFocus()"
    x-on:mousedown="$event.target.closest('[data-quick-codex-panel]') || restoreEditorFocus()"
>
    <x-dialog name="quick-codex-entry" :title="__('New codex entry')" max-width="md">
        <div class="space-y-4" data-quick-codex-panel>
            <div>
                <x-input-label for="quick-codex-entry-name" :value="__('Name')" />
                <x-text-input
                    id="quick-codex-entry-name"
                    type="text"
                    class="mt-1 block w-full"
                    x-model="name"
                    x-on:keydown.enter.prevent="submit()"
                    required
                    autofocus
                />
            </div>

            <fieldset>
                <legend class="block font-medium text-sm text-content-muted">{{ __('Type') }}</legend>

                <div class="mt-1 space-y-1">
                    @foreach (CodexEntryType::cases() as $type)
                        <label class="flex items-center gap-2">
                            <input type="radio" name="quick_codex_entry_type" value="{{ $type->value }}" x-model="type">
                            <span class="text-sm text-content-muted">{{ $type->label() }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <p class="text-sm text-danger" x-show="error" style="display: none;" role="alert">
                <span x-text="error"></span>
                <a
                    x-show="existingUrl"
                    x-bind:href="existingUrl"
                    class="text-link hover:text-link-hover underline"
                >{{ __('Open it') }}</a>
            </p>
        </div>

        <x-slot name="footer">
            <div data-quick-codex-panel class="flex gap-2">
                <x-button variant="secondary" type="button" x-on:click="close()">{{ __('Cancel') }}</x-button>
                <x-button variant="primary" type="button" x-on:click="submit()" x-bind:disabled="busy">{{ __('Create') }}</x-button>
            </div>
        </x-slot>
    </x-dialog>
</div>
