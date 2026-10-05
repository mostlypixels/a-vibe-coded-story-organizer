@php
    use App\Enums\CodexMediaCollection;

    $cover = $entry->media->firstWhere('collection', CodexMediaCollection::Cover);
    $referenceImages = $entry->media->where('collection', CodexMediaCollection::ReferenceImage)->sortBy('position')->values();
    $gallery = collect([$cover])->filter()->concat($referenceImages)->map(fn ($media) => [
        'url' => $media->url(),
        'alt' => $media->original_name ?? $entry->name,
    ])->values();
    $referenceFiles =$entry->media->where('collection', CodexMediaCollection::ReferenceFile)->sortBy('position')->values();
@endphp

<x-app-layout>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-heading level="1">{{ $entry->name }}</x-heading>
            <p class="text-sm text-content-muted">{{ $entry->type->label() }}</p>
        </div>

        <div class="flex shrink-0 items-center gap-1">
            <x-icon-edit-link :href="route('codex.edit', $entry)" />
            <x-icon-dialog-button icon="copy" variant="outline-solid" :modal="'duplicate-codex-entry-'.$entry->id" :label="__('Duplicate')" />
            <x-icon-button as="a" icon="history" variant="outline-solid" :label="__('History')" href="{{ route('revisions.index', ['entity' => 'codex', 'id' => $entry->id]) }}" />
            <x-icon-delete-button :action="route('codex.destroy', $entry)" :confirm="__('Are you sure you want to delete this entry?')" />
        </div>
    </div>

    <div class="space-y-6">
        @php($hasSidebar = $gallery->isNotEmpty() || $referenceFiles->isNotEmpty() || $entry->aliases->isNotEmpty() || $entry->tags->isNotEmpty())

        @if ($hasSidebar || filled($entry->description))
            <div class="grid gap-6 md:grid-cols-12">
                @if ($hasSidebar)
                    <div class="space-y-4 md:col-span-3">
                        @if ($gallery->isNotEmpty())
                            <div x-data="{ images: @js($gallery), current: 0 }">
                                <button
                                    type="button"
                                    @click="$dispatch('open-lightbox', { url: images[current].url, alt: images[current].alt })"
                                    class="block w-full rounded-md focus:outline-hidden focus:ring-2 focus:ring-focus focus:ring-offset-2"
                                >
                                    <img :src="images[current].url" :alt="images[current].alt" class="aspect-square w-full rounded-md border border-border object-cover">
                                </button>

                                @if ($gallery->count() > 1)
                                    <ul class="mt-2 grid grid-cols-4 gap-2">
                                        <template x-for="(image, index) in images" :key="index">
                                            <li>
                                                <button type="button" @click="current = index" :aria-label="image.alt" :aria-current="current === index" class="block w-full rounded-md focus-visible:outline-2 focus-visible:outline-offset-2">
                                                    <img :src="image.url" :alt="image.alt" class="aspect-square w-full rounded-md border object-cover" :class="current === index ? 'border-accent' : 'border-border'">
                                                </button>
                                            </li>
                                        </template>
                                    </ul>
                                @endif
                            </div>

                            <x-image-lightbox />
                        @endif

                        @if ($entry->aliases->isNotEmpty())
                            <x-card :title="__('Also known as')" icon="tabler-id">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($entry->aliases as $alias)
                                        <x-badge>{{ $alias->alias }}</x-badge>
                                    @endforeach
                                </div>
                            </x-card>
                        @endif

                        @if ($entry->tags->isNotEmpty())
                            <x-card :title="__('Tags')" icon="tabler-tags">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($entry->tags as $tag)
                                        <a href="{{ route('projects.codex.index', [$project, $entry->type->routeKey(), 'tag' => $tag->id]) }}" class="hover:opacity-80">
                                            <x-badge variant="accent">{{ $tag->name }}</x-badge>
                                        </a>
                                    @endforeach
                                </div>
                            </x-card>
                        @endif

                        @if ($referenceFiles->isNotEmpty())
                            <x-card :title="__('Reference files')" icon="tabler-paperclip">
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
                    </div>
                @endif

                @if (filled($entry->description))
                    <div class="{{ $hasSidebar ? 'md:col-span-9' : 'md:col-span-12' }}">
                        <x-card :title="__('Description')" icon="tabler-align-left">
                            <x-rich-text :html="$entry->description" />
                        </x-card>
                    </div>
                @endif
            </div>
        @endif

        @include('codex.partials.attribute-values')

        @if ($entry->inceptionEvent || $entry->terminationEvent)
            <x-card :title="__('Lifespan')" icon="tabler-hourglass">
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
            <x-card :title="__('Referenced in scenes')" icon="entity-scene">
                <x-references.scene-table
                    :scenes="$referencingScenes->take(config('search.cap'))"
                    :show-book="$showBook"
                    :see-all-route="$referencingScenes->count() > config('search.cap') ? route('codex.scenes.index', $entry) : null"
                    :see-all-count="$referencingScenes->count()"
                />
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
