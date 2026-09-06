<x-app-layout>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-heading level="1">{{ __('Act :number', ['number' => $numbering->act($act)]) }} &mdash; {{ $act->name }}</x-heading>
            <p class="text-sm text-content-muted">
                {{ $act->book->displayName() }}
                &middot;
                {{ trans_choice('{1} :count scene|[2,*] :count scenes', $act->scenes_count, ['count' => $act->scenes_count]) }}
                &middot;
                <x-word-count :count="$wordCount" variant="inline" />
            </p>
        </div>

        <div class="flex shrink-0 items-center gap-1">
            <x-icon-edit-link :href="route('acts.edit', $act)" />
            <x-icon-button as="a" icon="history" variant="outline-solid" :label="__('History')" href="{{ route('revisions.index', ['entity' => 'act', 'id' => $act->id]) }}" />
            @if ($act->chapters_count > 0)
                <x-icon-dialog-button :modal="'delete-act-'.$act->id" />
            @else
                <x-icon-delete-button :action="route('acts.destroy', $act)" :confirm="__('Are you sure you want to delete this act?')" />
            @endif
        </div>
    </div>

    <div class="space-y-6">
        @if (filled($act->description))
            <x-card :title="__('Description')">
                <x-rich-text :html="$act->description" />
            </x-card>
        @endif

        @if ($act->chapters->isNotEmpty())
            <x-card :title="__('Chapters')">
                <div x-data="{ showAll: false }">
                    @php $chapters = $act->chapters->sortBy('position')->values(); @endphp
                    <x-table>
                        <x-slot:head>
                            <x-table-heading>{{ __('Chapter') }}</x-table-heading>
                            <x-table-heading>{{ __('Scenes') }}</x-table-heading>
                        </x-slot:head>

                        @foreach ($chapters as $chapter)
                            <x-table-row :striped="$loop->even" x-show="{{ $loop->index < 20 ? 'true' : 'showAll' }}">
                                <x-table-cell>
                                    <a href="{{ route('chapters.show', $chapter) }}" class="text-link hover:text-link-hover">{{ $chapter->name }}</a>
                                </x-table-cell>
                                <x-table-cell muted>
                                    @foreach ($chapter->scenes->sortBy('position')->values() as $scene)
                                        <a href="{{ route('scenes.show', $scene) }}" class="text-link hover:text-link-hover">{{ $scene->name }}</a>@if (! $loop->last), @endif
                                    @endforeach
                                </x-table-cell>
                            </x-table-row>
                        @endforeach
                    </x-table>

                    @if ($chapters->count() > 20)
                        <button type="button" x-show="! showAll" x-on:click="showAll = true" class="mt-2 text-sm text-link hover:text-link-hover">
                            {{ __('Show all :count', ['count' => $chapters->count()]) }}
                        </button>
                    @endif
                </div>
            </x-card>
        @endif
    </div>

    @if ($act->chapters_count > 0)
        <x-delete-with-move-dialog
            name="delete-act-{{ $act->id }}"
            :action="route('acts.destroy', $act)"
            :title="__('Delete Act?')"
            :child-count="$act->chapters_count"
            child-singular="chapter"
            child-plural="chapters"
            destination-noun="act"
            :secondary-count="$act->scenes_count"
            secondary-singular="scene"
            secondary-plural="scenes"
            :destinations="$destinationActs"
        />
    @endif
</x-app-layout>
