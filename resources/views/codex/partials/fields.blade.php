@php
    use App\Enums\CodexMediaCollection;
    use App\Support\CodexMediaRules;

    // Shared body for the create and edit entry forms. $entry is null on create.
    $entry = $entry ?? null;
    $attributes = $attributes ?? collect();
    $projectTags = $projectTags ?? collect();
    $aliasValues = old('aliases', $entry?->aliases->pluck('alias')->values()->all() ?? []);
    $tagValues = old('tags', $entry?->tags->pluck('name')->values()->all() ?? []);

    // Media is loaded via the `media` relation on edit; derive each collection from it
    // (no extra queries) so the view stays dumb. All empty on create.
    $mediaItems = $entry?->media ?? collect();
    $cover = $mediaItems->firstWhere('collection', CodexMediaCollection::Cover);
    $referenceImages = $mediaItems->where('collection', CodexMediaCollection::ReferenceImage)->sortBy('position')->values();
    $referenceFiles = $mediaItems->where('collection', CodexMediaCollection::ReferenceFile)->sortBy('position')->values();
@endphp

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    {{-- Left column: main content --}}
    <div class="lg:col-span-9 space-y-6">
        <x-card>
            <div class="space-y-6">
                <div>
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $entry?->name)" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="description" :value="__('Description')" />
                    <x-wysiwyg id="description" name="description" :value="old('description', $entry?->description)" :rows="10" />
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>

                {{-- Aliases: a small add/remove-row repeater of free-text inputs (x-string-list). --}}
                <div>
                    <x-input-label :value="__('Aliases')" />
                    <p class="text-sm text-gray-500">{{ __('Other names this entry is known by (optional).') }}</p>

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

        {{-- Attribute baselines: create only. Each applicable attribute captures its Start value;
             later periods are added on the edit page once the entry (and its id) exist. --}}
        @if ($entry === null && $attributes->isNotEmpty())
            <x-card :title="__('Attributes')">
                <p class="text-sm text-gray-500">{{ __('Starting value for each attribute (from the Start of the timeline). You can add later changes after saving.') }}</p>

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
    </div>

    {{-- Right column: cover above tags. --}}
    <div class="lg:col-span-3 space-y-6">
        <x-card :title="__('Cover')">
            @if ($cover)
                <img src="{{ $cover->url() }}" alt="{{ $entry->name }}" class="w-full rounded-md border border-gray-200 object-cover">

                <label class="mt-2 flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="remove_media[]" value="{{ $cover->id }}" class="rounded border-gray-300 text-ocean-600 focus:ring-ocean-500">
                    {{ __('Remove cover') }}
                </label>
            @endif

            <div class="mt-3">
                <x-input-label for="cover" :value="$cover ? __('Replace cover') : __('Upload cover')" />
                <input id="cover" name="cover" type="file" accept="{{ CodexMediaRules::imageAccept() }}" class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                <p class="mt-1 text-xs text-gray-400">{{ CodexMediaRules::imageHint() }}</p>
                <x-input-error :messages="$errors->get('cover')" class="mt-2" />
            </div>
        </x-card>

        <x-card :title="__('Tags')">
            {{-- Autocomplete chip picker: existing project tags plus free-text new names. --}}
            <x-tag-picker name="tags" :tags="$projectTags" :selected="$tagValues" />
            <x-input-error :messages="$errors->get('tags')" class="mt-2" />
        </x-card>
    </div>
</div>

{{--
    Reference media: full-width block below the two columns, above the Save button.
    One shared multipart form saves reference uploads and any per-item removals
    (checkboxes feeding remove_media[]) alongside everything else in a single Save.

    Tabs are inline Alpine (matching resources/views/admin/data/index.blade.php:
    no reusable x-tabs component until a second screen needs one) and follow the
    WAI-ARIA tabs pattern: role="tablist"/tab/tabpanel, aria-selected on the active
    tab, aria-controls wiring tab -> panel, a roving tabindex (only the active tab
    is in the tab order), and Left/Right arrow keys move between tabs. `activeTab`
    is the single source of truth for which tab/panel shows.

    The lightbox reuses the same x-data scope: clicking a reference image sets
    `lightbox` to its url/alt and an overlay shows it full-size. Pre-mount state is
    hidden with style="display:none" (no x-cloak), matching the other interactive
    components (see resources/views/components/wysiwyg.blade.php).
--}}
<div class="mt-6" x-data="{ activeTab: 'images', lightbox: null, filePreview: null }">
    <x-card>
        <div class="border-b border-gray-200">
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
                        ? 'border-ocean-500 text-gray-900'
                        : 'border-transparent text-gray-500 hover:text-gray-900 hover:border-gray-300'"
                    class="inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-ocean-500 focus:ring-offset-2 rounded-sm transition ease-in-out duration-150"
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
                        ? 'border-ocean-500 text-gray-900'
                        : 'border-transparent text-gray-500 hover:text-gray-900 hover:border-gray-300'"
                    class="inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-ocean-500 focus:ring-offset-2 rounded-sm transition ease-in-out duration-150"
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
            class="mt-6 focus:outline-none"
        >
            @if ($referenceImages->isNotEmpty())
                <ul class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                    @foreach ($referenceImages as $image)
                        <li>
                            <button
                                type="button"
                                @click="lightbox = { url: @js($image->url()), alt: @js($image->original_name) }"
                                class="block w-full focus:outline-none focus:ring-2 focus:ring-ocean-500 focus:ring-offset-2 rounded-md"
                            >
                                <img src="{{ $image->url() }}" alt="{{ $image->original_name }}" class="w-full aspect-square rounded-md border border-gray-200 object-cover">
                            </button>
                            <label class="mt-1 flex items-center gap-1 text-xs text-gray-600">
                                <input type="checkbox" name="remove_media[]" value="{{ $image->id }}" class="rounded border-gray-300 text-ocean-600 focus:ring-ocean-500">
                                {{ __('Remove') }}
                            </label>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-3">
                <x-input-label for="reference_images" :value="__('Add images')" />
                <input id="reference_images" name="reference_images[]" type="file" multiple accept="{{ CodexMediaRules::imageAccept() }}" class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                <p class="mt-1 text-xs text-gray-400">{{ CodexMediaRules::imageHint() }}</p>
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
            class="mt-6 focus:outline-none"
        >
            @if ($referenceFiles->isNotEmpty())
                <ul class="space-y-2">
                    @foreach ($referenceFiles as $file)
                        <li class="flex items-center justify-between gap-2 text-sm">
                            <span class="flex min-w-0 items-center gap-3">
                                <button
                                    type="button"
                                    @click="filePreview = { url: @js($file->url()), name: @js($file->original_name) }"
                                    class="truncate text-ocean-600 hover:text-ocean-800 focus:outline-none focus:ring-2 focus:ring-ocean-500 rounded-sm"
                                >
                                    {{ $file->original_name }}
                                </button>
                                <a href="{{ $file->url() }}" class="shrink-0 text-xs text-gray-500 hover:text-gray-700" download="{{ $file->original_name }}">{{ __('Download') }}</a>
                            </span>
                            <label class="flex shrink-0 items-center gap-1 text-xs text-gray-600">
                                <input type="checkbox" name="remove_media[]" value="{{ $file->id }}" class="rounded border-gray-300 text-ocean-600 focus:ring-ocean-500">
                                {{ __('Remove') }}
                            </label>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-3">
                <x-input-label for="reference_files" :value="__('Add files')" />
                <input id="reference_files" name="reference_files[]" type="file" multiple accept="{{ CodexMediaRules::fileAccept() }}" class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                <p class="mt-1 text-xs text-gray-400">{{ CodexMediaRules::fileHint() }}</p>
                <x-input-error :messages="$errors->get('reference_files')" class="mt-2" />
                <x-input-error :messages="$errors->get('reference_files.*')" class="mt-2" />
            </div>
        </div>
    </x-card>

    {{-- Lightbox: shows the clicked reference image full-size. Local Alpine state
         (`lightbox`), not the global x-modal component, since it needs to carry
         per-image data (url/alt) rather than just an open/closed name. --}}
    <div
        x-show="lightbox"
        style="display: none"
        @keydown.escape.window="lightbox = null"
        class="fixed inset-0 z-50 overflow-y-auto px-4 py-6"
        role="dialog"
        aria-modal="true"
    >
        <div class="fixed inset-0 bg-gray-500 opacity-75" @click="lightbox = null"></div>

        <div class="relative mx-auto max-w-3xl">
            <button
                type="button"
                @click="lightbox = null"
                class="absolute -top-10 right-0 text-sm font-medium text-white hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-white rounded-sm"
            >
                {{ __('Close') }}
            </button>
            <img :src="lightbox?.url" :alt="lightbox?.alt" class="w-full rounded-lg shadow-xl">
        </div>
    </div>

    {{-- File preview: shows the clicked reference file in an iframe. Same local-state
         pattern as the image lightbox; PDFs and text/markdown files render inline via
         the browser's built-in viewer, other allowed types (doc/docx) fall back to
         their native "download this" prompt inside the frame — the Download link next
         to the filename is still the reliable path for those. --}}
    <div
        x-show="filePreview"
        style="display: none"
        @keydown.escape.window="filePreview = null"
        class="fixed inset-0 z-50 overflow-y-auto px-4 py-6"
        role="dialog"
        aria-modal="true"
    >
        <div class="fixed inset-0 bg-gray-500 opacity-75" @click="filePreview = null"></div>

        <div class="relative mx-auto flex h-full max-w-4xl flex-col">
            <div class="flex items-center justify-between rounded-t-lg bg-white px-4 py-2 shadow-xl">
                <span class="truncate text-sm font-medium text-gray-700" x-text="filePreview?.name"></span>
                <span class="flex shrink-0 items-center gap-3">
                    <a :href="filePreview?.url" :download="filePreview?.name" class="text-sm text-ocean-600 hover:text-ocean-800">{{ __('Download') }}</a>
                    <button
                        type="button"
                        @click="filePreview = null"
                        class="text-sm font-medium text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-ocean-500 rounded-sm"
                    >
                        {{ __('Close') }}
                    </button>
                </span>
            </div>
            <iframe :src="filePreview?.url" :title="filePreview?.name" class="flex-1 rounded-b-lg border-0 bg-white shadow-xl"></iframe>
        </div>
    </div>
</div>
