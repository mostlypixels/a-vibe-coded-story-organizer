{{-- The Links card on the note edit page. It sits outside the note <form>: Alpine posts it. --}}
@php
    use App\Enums\NoteLinkType;
@endphp

<div
    x-data="linkPicker({
        candidatesUrl: @js(route('notes.link-candidates', $note)),
        storeUrl: @js(route('notes.links.store', $note)),
        type: @js(NoteLinkType::Scene->value),
        dialog: 'note-link',
        refreshSelector: '[data-note-links]',
        failureMessage: @js(__('The link was not saved. Check your connection and try again.')),
        searchFailureMessage: @js(__('The search failed. Check your connection and try again.')),
    })"
>
    <x-card :title="__('Links')" icon="tabler-link">
        <div data-note-links>
            @include('notes.partials.link-list', ['note' => $note, 'groups' => $linkGroups, 'editable' => true])
        </div>

        <p class="mt-3 text-sm text-danger" x-show="error" style="display: none;" role="alert" x-text="error"></p>

        <x-slot:footer>
            <x-button variant="secondary" type="button" x-on:click="open()">{{ __('Add link') }}</x-button>
        </x-slot:footer>
    </x-card>

    <x-dialog name="note-link" :title="__('Add link')" max-width="md">
        <div class="space-y-4">
            <div>
                <x-input-label for="note-link-type" :value="__('Type')" />
                <x-select id="note-link-type" class="mt-1 block w-full sm:text-sm" x-model="type" x-on:change="search()">
                    @foreach (NoteLinkType::cases() as $type)
                        <option value="{{ $type->value }}">{{ __($type->label()) }}</option>
                    @endforeach
                </x-select>
            </div>

            <div>
                <x-input-label for="note-link-query" :value="__('Search')" />
                <x-text-input id="note-link-query" type="search" class="mt-1 block w-full" x-model="query" x-on:input="queueSearch()" x-on:keydown.enter.prevent="search()" autocomplete="off" />
            </div>

            <ul class="max-h-64 space-y-1 overflow-y-auto" aria-live="polite" x-bind:aria-busy="searching">
                <template x-for="candidate in results" x-bind:key="candidate.type + ':' + candidate.id">
                    <li>
                        <button type="button" class="w-full rounded-md px-2 py-1 text-left text-sm text-content hover:bg-neutral disabled:opacity-50" x-text="label(candidate)" x-on:click="pick(candidate)" x-bind:disabled="busy"></button>
                    </li>
                </template>
                <li class="px-2 text-sm text-content-muted" x-show="!searching && results.length === 0" style="display: none;">{{ __('Nothing found.') }}</li>
            </ul>

            <p class="text-sm text-danger" x-show="error" style="display: none;" role="alert" x-text="error"></p>
        </div>

        <x-slot name="footer">
            <x-button variant="secondary" type="button" x-on:click="close()">{{ __('Close') }}</x-button>
        </x-slot>
    </x-dialog>
</div>
