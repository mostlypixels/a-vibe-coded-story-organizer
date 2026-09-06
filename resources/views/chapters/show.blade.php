@php
    $coverUrl = $chapter->cover_image
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($chapter->cover_image)
        : null;
    $scenes = $chapter->scenes->sortBy('position')->values();
@endphp

<x-app-layout>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-start gap-4">
            @if ($coverUrl)
                <img src="{{ $coverUrl }}" alt="{{ $chapter->name }}" class="h-20 w-20 shrink-0 rounded-md border border-border object-cover">
            @endif

            <div>
                <x-heading level="1">{{ __('Chapter :number', ['number' => $numbering->chapter($chapter)]) }} &mdash; {{ $chapter->name }}</x-heading>
                <p class="text-sm text-content-muted">
                    {{ $chapter->act->name }}
                    &middot;
                    {{ trans_choice('{1} :count scene|[2,*] :count scenes', $chapter->scenes_count, ['count' => $chapter->scenes_count]) }}
                    &middot;
                    <x-word-count :count="$chapter->word_count" variant="inline" />
                </p>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-1">
            <x-icon-edit-link :href="route('chapters.edit', $chapter)" />
            <x-icon-button as="a" icon="history" variant="outline-solid" :label="__('History')" href="{{ route('revisions.index', ['entity' => 'chapter', 'id' => $chapter->id]) }}" />
            @if ($chapter->scenes_count > 0)
                <x-icon-dialog-button :modal="'delete-chapter-'.$chapter->id" />
            @else
                <x-icon-delete-button :action="route('chapters.destroy', $chapter)" :confirm="__('Are you sure you want to delete this chapter?')" />
            @endif
        </div>
    </div>

    <div class="space-y-6">
        @if (filled($chapter->description))
            <x-card :title="__('Description')">
                <x-rich-text :html="$chapter->description" />
            </x-card>
        @endif

        @if ($scenes->isNotEmpty())
            <x-card :title="__('Scenes')">
                <x-table>
                    <x-slot:head>
                        <x-table-heading>{{ __('Scene') }}</x-table-heading>
                        <x-table-heading class="text-right">{{ __('Words') }}</x-table-heading>
                    </x-slot:head>

                    @foreach ($scenes as $scene)
                        <x-table-row :striped="$loop->even">
                            <x-table-cell>
                                <a href="{{ route('scenes.show', $scene) }}" class="text-link hover:text-link-hover">{{ $scene->name }}</a>
                            </x-table-cell>
                            <x-table-cell align="right" muted nowrap>
                                <x-word-count :count="$scene->word_count" variant="inline" />
                            </x-table-cell>
                        </x-table-row>
                    @endforeach
                </x-table>
            </x-card>
        @endif
    </div>

    @if ($chapter->scenes_count > 0)
        <x-delete-with-move-dialog
            name="delete-chapter-{{ $chapter->id }}"
            :action="route('chapters.destroy', $chapter)"
            :title="__('Delete Chapter?')"
            :child-count="$chapter->scenes_count"
            child-singular="scene"
            child-plural="scenes"
            destination-noun="chapter"
            :destinations="$destinationChapters"
        />
    @endif
</x-app-layout>
