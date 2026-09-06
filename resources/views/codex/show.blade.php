@php
    use App\Enums\CodexMediaCollection;

    $cover = $entry->media->firstWhere('collection', CodexMediaCollection::Cover);
    $referenceImages = $entry->media->where('collection', CodexMediaCollection::ReferenceImage)->sortBy('position')->values();
    $referenceFiles = $entry->media->where('collection', CodexMediaCollection::ReferenceFile)->sortBy('position')->values();
@endphp

<x-app-layout>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-start gap-4">
            @if ($cover)
                <img src="{{ $cover->url() }}" alt="{{ $entry->name }}" class="h-20 w-20 shrink-0 rounded-md border border-border object-cover">
            @endif

            <div>
                <x-heading level="1">{{ $entry->name }}</x-heading>
                <p class="text-sm text-content-muted">{{ $entry->type->label() }}</p>

                {{-- Labelled separately: badge colour alone does not say where the aliases end. --}}
                <div class="mt-2 space-y-1">
                    @if ($entry->aliases->isNotEmpty())
                        <div class="flex flex-wrap items-center gap-1">
                            <span class="mr-1 text-xs text-content-muted">{{ __('Also known as') }}</span>
                            @foreach ($entry->aliases as $alias)
                                <x-badge>{{ $alias->alias }}</x-badge>
                            @endforeach
                        </div>
                    @endif

                    @if ($entry->tags->isNotEmpty())
                        <div class="flex flex-wrap items-center gap-1">
                            <span class="mr-1 text-xs text-content-muted">{{ __('Tags') }}</span>
                            @foreach ($entry->tags as $tag)
                                <x-badge variant="accent">{{ $tag->name }}</x-badge>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-1">
            <x-icon-edit-link :href="route('codex.edit', $entry)" />
            <x-icon-dialog-button icon="copy" variant="outline-solid" :modal="'duplicate-codex-entry-'.$entry->id" :label="__('Duplicate')" />
            <x-icon-button as="a" icon="history" variant="outline-solid" :label="__('History')" href="{{ route('revisions.index', ['entity' => 'codex', 'id' => $entry->id]) }}" />
            <x-icon-delete-button :action="route('codex.destroy', $entry)" :confirm="__('Are you sure you want to delete this entry?')" />
        </div>
    </div>

    <div class="space-y-6">
        @if (filled($entry->description))
            <x-card :title="__('Description')">
                <x-rich-text :html="$entry->description" />
            </x-card>
        @endif

        @if ($referenceImages->isNotEmpty())
            <x-card :title="__('Reference images')">
                <ul class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                    @foreach ($referenceImages as $image)
                        <li>
                            <a href="{{ $image->url() }}" target="_blank" rel="noopener">
                                <img src="{{ $image->url() }}" alt="{{ $image->original_name }}" class="aspect-square w-full rounded-md border border-border object-cover">
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        @if ($referenceFiles->isNotEmpty())
            <x-card :title="__('Reference files')">
                <ul class="space-y-2">
                    @foreach ($referenceFiles as $file)
                        <li class="flex items-center justify-between gap-2 text-sm">
                            <span class="truncate">{{ $file->original_name }}</span>
                            <x-icon-download-button :href="$file->url()" :download="$file->original_name" class="shrink-0" />
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        @include('codex.partials.attribute-values')

        @if ($entry->inceptionEvent || $entry->terminationEvent)
            <x-card :title="__('Lifespan')">
                <dl class="space-y-1 text-sm">
                    @if ($entry->inceptionEvent)
                        <div class="flex gap-2">
                            <dt class="text-content-muted">{{ $entry->type->inceptionLabel() }}:</dt>
                            <dd class="text-content">{{ $entry->inceptionEvent->title }} &mdash; <x-date :value="$entry->inceptionEvent->event_datetime" /></dd>
                        </div>
                    @endif

                    @if ($entry->terminationEvent)
                        <div class="flex gap-2">
                            <dt class="text-content-muted">{{ $entry->type->terminationLabel() }}:</dt>
                            <dd class="text-content">{{ $entry->terminationEvent->title }} &mdash; <x-date :value="$entry->terminationEvent->event_datetime" /></dd>
                        </div>
                    @endif
                </dl>
            </x-card>
        @endif

        @if ($referencingScenes->isNotEmpty())
            <x-card :title="__('Referenced in scenes')">
                <div x-data="{ showAll: false }">
                    <x-table>
                        <x-slot:head>
                            <x-table-heading>{{ __('Scene') }}</x-table-heading>
                            <x-table-heading>{{ __('Chapter') }}</x-table-heading>
                            <x-table-heading>{{ __('Act') }}</x-table-heading>
                        </x-slot:head>

                        @foreach ($referencingScenes as $scene)
                            <x-table-row :striped="$loop->even" x-show="{{ $loop->index < 20 ? 'true' : 'showAll' }}">
                                <x-table-cell>
                                    <a href="{{ route('scenes.show', $scene) }}" class="text-link hover:text-link-hover">{{ $scene->name }}</a>
                                </x-table-cell>
                                <x-table-cell muted>{{ $scene->chapter->name }}</x-table-cell>
                                <x-table-cell muted>{{ $scene->chapter->act->name }}</x-table-cell>
                            </x-table-row>
                        @endforeach
                    </x-table>

                    @if ($referencingScenes->count() > 20)
                        <button
                            type="button"
                            x-show="! showAll"
                            x-on:click="showAll = true"
                            class="mt-2 text-sm text-link hover:text-link-hover"
                        >
                            {{ __('Show all :count', ['count' => $referencingScenes->count()]) }}
                        </button>
                    @endif
                </div>
            </x-card>
        @endif
    </div>

    <x-duplicate-dialog
        name="duplicate-codex-entry-{{ $entry->id }}"
        :action="route('codex.duplicate', $entry)"
        :title="__('Duplicate :label', ['label' => $entry->type->label()])"
        :suggestion="$entry->name"
    />
</x-app-layout>
