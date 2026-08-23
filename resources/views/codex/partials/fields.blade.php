@php
    use App\Enums\CodexMediaCollection;
    use App\Support\CodexMediaRules;

    $entry = $entry ?? null;
    $attributes = $attributes ?? collect();
    $projectTags = $projectTags ?? collect();
    $aliasValues = old('aliases', $entry?->aliases->pluck('alias')->values()->all() ?? []);
    $tagValues = old('tags', $entry?->tags->pluck('name')->values()->all() ?? []);

    $mediaItems = $entry?->media ?? collect();
    $cover = $mediaItems->firstWhere('collection', CodexMediaCollection::Cover);
    $referenceImages = $mediaItems->where('collection', CodexMediaCollection::ReferenceImage)->sortBy('position')->values();
    $referenceFiles = $mediaItems->where('collection', CodexMediaCollection::ReferenceFile)->sortBy('position')->values();
@endphp

<x-edit-layout>
    <x-card>
        <div class="space-y-6">
            <div>
                <x-input-label for="name" :value="__('Name')" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $entry?->name)" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                @if ($entry !== null)
                    <x-autosave-field entity="codex" :model="$entry" field="description" :label="__('Description')" :rows="10" />
                @else
                    <x-input-label for="description" :value="__('Description')" />
                    <x-wysiwyg id="description" name="description" :value="old('description', $entry?->description)" :rows="10" />
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                @endif
            </div>

            <div>
                <x-input-label :value="__('Aliases')" />
                <p class="text-sm text-content-muted">{{ __('Other names this entry is known by (optional).') }}</p>
                <p class="text-sm text-content-muted">{{ __('Scenes are scanned for these names automatically when saved. Matching is case-sensitive and whole-word only, and aliases under 3 characters are ignored. If aliases overlap with another entry\'s name or alias, matches can be ambiguous.') }}</p>

                <x-string-list
                    name="aliases"
                    :values="$aliasValues"
                    :placeholder="__('e.g. The Serpent Lady')"
                    :add-label="__('+ Add alias')"
                    :remove-label="__('Remove alias')"
                />
                <x-input-error :messages="$errors->get('aliases')" class="mt-2" />
            </div>
        </div>
    </x-card>

    @if ($entry !== null && $type->tracksLifespan())
        <x-card :title="__('Existence')">
            <div class="space-y-6">
                <x-single-event-field
                    name="inception_event_id"
                    :label="$type->inceptionLabel()"
                    :events="$regularEvents"
                    :selected="$entry->inception_event_id"
                    :empty-label="__('— Not set —')"
                    :window-min="$windowMin"
                    :window-max="$windowMax"
                />

                <x-single-event-field
                    name="termination_event_id"
                    :label="$type->terminationLabel()"
                    :events="$regularEvents"
                    :selected="$entry->termination_event_id"
                    :empty-label="__('— Not set —')"
                    :window-min="$windowMin"
                    :window-max="$windowMax"
                >
                    @if ($entry->hasInvertedLifespan())
                        <p class="mt-2 text-sm text-content-subtle">
                            {{ __('Termination is before inception, so age is not calculated. Track age with an attribute instead.') }}
                        </p>
                    @endif
                </x-single-event-field>
            </div>
        </x-card>
    @endif

    @if ($entry === null && $attributes->isNotEmpty())
        <x-card :title="__('Attributes')">
            <p class="text-sm text-content-muted">{{ __('Starting value for each attribute (from the Start of the timeline). You can add later changes after saving.') }}</p>

            <div class="mt-4 space-y-4">
                @foreach ($attributes as $attribute)
                    <div>
                        <x-input-label
                            for="attribute_baselines_{{ $attribute->id }}"
                            :value="__(':name (from Start)', ['name' => $attribute->name])"
                        />
                        <x-text-input
                            id="attribute_baselines_{{ $attribute->id }}"
                            name="attribute_baselines[{{ $attribute->id }}]"
                            type="text"
                            class="mt-1 block w-full"
                            :value="old('attribute_baselines.'.$attribute->id)"
                        />
                        <x-input-error :messages="$errors->get('attribute_baselines.'.$attribute->id)" class="mt-2" />
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    <x-slot:sidebar>
        @if ($entry === null)
            <x-create-actions form="codex-entry-create-form" :cancel="route('projects.codex.index', [$project, $type->routeKey()])">
                {{ __('Create :label', ['label' => $type->label()]) }}
            </x-create-actions>
        @else
            <x-edit-actions
                form="codex-entry-edit-form"
                :history-model="$entry"
                duplicate-modal="duplicate-codex-entry-{{ $entry->id }}"
            >
                <x-slot:delete>
                    <x-button variant="danger" type="submit" form="codex-entry-delete-form" :icon="true" class="w-full">
                        {{ __('Delete :label', ['label' => $type->label()]) }}
                    </x-button>
                </x-slot:delete>
            </x-edit-actions>
        @endif

        <x-card :title="__('Cover')">
            @if ($cover)
                <img src="{{ $cover->url() }}" alt="{{ $entry->name }}" class="w-full rounded-md border border-border object-cover">

                <label class="mt-2 flex items-center gap-2 text-sm text-content-muted">
                    <input type="checkbox" name="remove_media[]" value="{{ $cover->id }}" class="rounded-sm border-border-strong text-link focus:ring-focus">
                    {{ __('Remove cover') }}
                </label>
            @endif

            <div class="mt-3">
                <x-input-label for="cover" :value="$cover ? __('Replace cover') : __('Upload cover')" />
                <input id="cover" name="cover" type="file" accept="{{ CodexMediaRules::imageAccept() }}" class="mt-1 block w-full text-sm text-content-muted file:mr-3 file:rounded-md file:border-0 file:bg-neutral file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-neutral-content hover:file:bg-neutral/80">
                <p class="mt-1 text-xs text-content-subtle">{{ CodexMediaRules::imageHint() }}</p>
                <x-input-error :messages="$errors->get('cover')" class="mt-2" />
            </div>
        </x-card>

        <x-card :title="__('Tags')" overflow="visible">
            <x-tag-picker name="tags" :tags="$projectTags" :selected="$tagValues" />
            <x-input-error :messages="$errors->get('tags')" class="mt-2" />
        </x-card>
    </x-slot:sidebar>
</x-edit-layout>

<div class="mt-6" x-data="{ activeTab: 'images', lightbox: null, filePreview: null }">
    <x-card>
        <div class="border-b border-border">
            <div role="tablist" aria-label="{{ __('Reference media') }}" class="-mb-px flex gap-2">
                <button
                    id="tab-reference-images"
                    type="button"
                    role="tab"
                    x-ref="tabImages"
                    aria-controls="panel-reference-images"
                    :aria-selected="activeTab === 'images' ? 'true' : 'false'"
                    :tabindex="activeTab === 'images' ? 0 : -1"
                    @click="activeTab = 'images'"
                    @keydown.right.prevent="activeTab = 'files'; $refs.tabFiles.focus()"
                    @keydown.left.prevent="activeTab = 'files'; $refs.tabFiles.focus()"
                    :class="activeTab === 'images'
                        ? 'border-accent text-content'
                        : 'border-transparent text-content-muted hover:text-content hover:border-border-strong'"
                    class="inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium focus:outline-hidden focus:ring-2 focus:ring-focus focus:ring-offset-2 rounded-xs transition ease-in-out duration-150"
                >
                    {{ __('Reference images') }}
                </button>

                <button
                    id="tab-reference-files"
                    type="button"
                    role="tab"
                    x-ref="tabFiles"
                    aria-controls="panel-reference-files"
                    :aria-selected="activeTab === 'files' ? 'true' : 'false'"
                    :tabindex="activeTab === 'files' ? 0 : -1"
                    @click="activeTab = 'files'"
                    @keydown.right.prevent="activeTab = 'images'; $refs.tabImages.focus()"
                    @keydown.left.prevent="activeTab = 'images'; $refs.tabImages.focus()"
                    :class="activeTab === 'files'
                        ? 'border-accent text-content'
                        : 'border-transparent text-content-muted hover:text-content hover:border-border-strong'"
                    class="inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium focus:outline-hidden focus:ring-2 focus:ring-focus focus:ring-offset-2 rounded-xs transition ease-in-out duration-150"
                >
                    {{ __('Reference files') }}
                </button>
            </div>
        </div>

        <div
            id="panel-reference-images"
            role="tabpanel"
            aria-labelledby="tab-reference-images"
            tabindex="0"
            x-show="activeTab === 'images'"
            class="mt-6 focus:outline-hidden"
        >
            @if ($referenceImages->isNotEmpty())
                <ul class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                    @foreach ($referenceImages as $image)
                        <li>
                            <button
                                type="button"
                                @click="lightbox = { url: @js($image->url()), alt: @js($image->original_name) }"
                                class="block w-full focus:outline-hidden focus:ring-2 focus:ring-focus focus:ring-offset-2 rounded-md"
                            >
                                <img src="{{ $image->url() }}" alt="{{ $image->original_name }}" class="w-full aspect-square rounded-md border border-border object-cover">
                            </button>
                            <label class="mt-1 flex items-center gap-1 text-xs text-content-muted">
                                <input type="checkbox" name="remove_media[]" value="{{ $image->id }}" class="rounded-sm border-border-strong text-link focus:ring-focus">
                                {{ __('Remove') }}
                            </label>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-3">
                <x-input-label for="reference_images" :value="__('Add images')" />
                <input id="reference_images" name="reference_images[]" type="file" multiple accept="{{ CodexMediaRules::imageAccept() }}" class="mt-1 block w-full text-sm text-content-muted file:mr-3 file:rounded-md file:border-0 file:bg-neutral file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-neutral-content hover:file:bg-neutral/80">
                <p class="mt-1 text-xs text-content-subtle">{{ CodexMediaRules::imageHint() }}</p>
                <x-input-error :messages="$errors->get('reference_images')" class="mt-2" />
                <x-input-error :messages="$errors->get('reference_images.*')" class="mt-2" />
            </div>
        </div>

        <div
            id="panel-reference-files"
            role="tabpanel"
            aria-labelledby="tab-reference-files"
            tabindex="0"
            x-show="activeTab === 'files'"
            style="display: none"
            class="mt-6 focus:outline-hidden"
        >
            @if ($referenceFiles->isNotEmpty())
                <ul class="space-y-2">
                    @foreach ($referenceFiles as $file)
                        <li class="flex items-center justify-between gap-2 text-sm">
                            <span class="flex min-w-0 items-center gap-3">
                                <button
                                    type="button"
                                    @click="filePreview = { url: @js($file->url()), name: @js($file->original_name) }"
                                    class="truncate text-link hover:text-link-hover focus:outline-hidden focus:ring-2 focus:ring-focus rounded-xs"
                                >
                                    {{ $file->original_name }}
                                </button>
                                <x-icon-download-button :href="$file->url()" :download="$file->original_name" class="shrink-0" />
                            </span>
                            <label class="flex shrink-0 items-center gap-1 text-xs text-content-muted">
                                <input type="checkbox" name="remove_media[]" value="{{ $file->id }}" class="rounded-sm border-border-strong text-link focus:ring-focus">
                                {{ __('Remove') }}
                            </label>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-3">
                <x-input-label for="reference_files" :value="__('Add files')" />
                <input id="reference_files" name="reference_files[]" type="file" multiple accept="{{ CodexMediaRules::fileAccept() }}" class="mt-1 block w-full text-sm text-content-muted file:mr-3 file:rounded-md file:border-0 file:bg-neutral file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-neutral-content hover:file:bg-neutral/80">
                <p class="mt-1 text-xs text-content-subtle">{{ CodexMediaRules::fileHint() }}</p>
                <x-input-error :messages="$errors->get('reference_files')" class="mt-2" />
                <x-input-error :messages="$errors->get('reference_files.*')" class="mt-2" />
            </div>
        </div>
    </x-card>

    <div
        x-show="lightbox"
        style="display: none"
        @keydown.escape.window="lightbox = null"
        class="fixed inset-0 z-50 overflow-y-auto px-4 py-6"
        role="dialog"
        aria-modal="true"
    >
        <div class="fixed inset-0 bg-scrim opacity-75" @click="lightbox = null"></div>

        <div class="relative mx-auto max-w-3xl">
            <x-icon-close-button @click="lightbox = null" variant="light" class="absolute -top-10 right-0" />
            <img :src="lightbox?.url" :alt="lightbox?.alt" class="w-full rounded-lg shadow-xl">
        </div>
    </div>

    <div
        x-show="filePreview"
        style="display: none"
        @keydown.escape.window="filePreview = null"
        class="fixed inset-0 z-50 overflow-y-auto px-4 py-6"
        role="dialog"
        aria-modal="true"
    >
        <div class="fixed inset-0 bg-scrim opacity-75" @click="filePreview = null"></div>

        <div class="relative mx-auto flex h-full max-w-4xl flex-col">
            <div class="flex items-center justify-between rounded-t-lg bg-surface-raised px-4 py-2 shadow-xl">
                <span class="truncate text-sm font-medium text-content-muted" x-text="filePreview?.name"></span>
                <span class="flex shrink-0 items-center gap-1">
                    <x-icon-download-button x-bind:href="filePreview?.url" x-bind:download="filePreview?.name" />
                    <x-icon-close-button @click="filePreview = null" />
                </span>
            </div>
            <iframe :src="filePreview?.url" :title="filePreview?.name" class="flex-1 rounded-b-lg border-0 bg-surface-raised shadow-xl"></iframe>
        </div>
    </div>
</div>
