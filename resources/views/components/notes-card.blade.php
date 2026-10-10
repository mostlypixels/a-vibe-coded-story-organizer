{{-- Notes linked to one entity. The picker swaps the list under [data-linked-notes] after a change. --}}
<div
    x-data="linkPicker({
        candidatesUrl: @js(route('notes.candidates', $project)),
        storeUrl: @js(route('notes.links.store', ['note' => '__NOTE__'])),
        linkType: @js($type->value),
        linkId: @js($linkable->getKey()),
        dialog: 'link-note',
        refreshSelector: '[data-linked-notes]',
        failureMessage: @js(__('The link was not saved. Check your connection and try again.')),
        searchFailureMessage: @js(__('The search failed. Check your connection and try again.')),
    })"
>
    <x-card :title="__('Notes')" icon="tabler-note">
        <div data-linked-notes>
            @if ($notes->isEmpty())
                <p class="text-sm text-content-muted">{{ __('No notes are linked here yet.') }}</p>
            @else
                <ul class="divide-y divide-border">
                    @foreach ($notes as $note)
                        <li class="flex items-start justify-between gap-2 py-2 first:pt-0 last:pb-0" data-linked-note="{{ $note->id }}">
                            <div class="min-w-0">
                                <a href="{{ route('notes.show', $note) }}" class="text-link hover:text-link-hover">{{ $note->title }}</a>
                                @if (filled($note->body))
                                    <p class="text-sm text-content-muted"><x-rich-text-excerpt :html="$note->body" :limit="160" /></p>
                                @endif
                            </div>
                            <x-icon-button
                                type="button"
                                icon="unlink"
                                variant="ghost"
                                class="shrink-0"
                                :label="__('Unlink :title', ['title' => $note->title])"
                                x-bind:disabled="busy"
                                x-on:click="unlink({{ Js::from(route('notes.links.destroy', ['note' => $note, 'type' => $type->value, 'id' => $linkable->getKey()])) }})"
                            />
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <p class="mt-3 text-sm text-danger" x-show="error" style="display: none;" role="alert" x-text="error"></p>

        <x-slot:footer>
            <div class="flex flex-wrap gap-2">
                <x-button :href="$createUrl()" variant="secondary">{{ __('New note') }}</x-button>
                <x-button variant="secondary" type="button" x-on:click="open()">{{ __('Link a note') }}</x-button>
            </div>
        </x-slot:footer>
    </x-card>

    <x-dialog name="link-note" :title="__('Link a note')" max-width="md">
        <div class="space-y-4">
            <div>
                <x-input-label for="link-note-query" :value="__('Search')" />
                <x-text-input id="link-note-query" type="search" class="mt-1 block w-full" x-model="query" x-on:input="queueSearch()" x-on:keydown.enter.prevent="search()" autocomplete="off" />
            </div>

            <ul class="max-h-64 space-y-1 overflow-y-auto" aria-live="polite" x-bind:aria-busy="searching">
                <template x-for="candidate in results" x-bind:key="candidate.id">
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
