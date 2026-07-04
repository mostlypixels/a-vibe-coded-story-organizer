@php
    use App\Enums\CodexMediaCollection;
    use App\Support\CodexMediaRules;

    // Shared body for the create and edit entry forms. $entry is null on create.
    $entry = $entry ?? null;
    $attributes = $attributes ?? collect();
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
    <div class="lg:col-span-8 space-y-6">
        <x-card>
            <div class="space-y-6">
                <div>
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $entry?->name)" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="description" :value="__('Description (Markdown)')" />
                    <textarea id="description" name="description" rows="10" class="mt-1 block w-full font-mono text-sm border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm">{{ old('description', $entry?->description) }}</textarea>
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

    {{-- Middle column: tags & categories --}}
    <div class="lg:col-span-2 space-y-6">
        <x-card :title="__('Tags')">
            {{-- Autocomplete chip picker: existing project tags plus free-text new names. --}}
            <x-tag-picker name="tags" :tags="$project->tags()->orderBy('name')->get()" :selected="$tagValues" />
            <x-input-error :messages="$errors->get('tags')" class="mt-2" />
        </x-card>
    </div>

    {{-- Right column: media. One shared multipart form saves cover + reference uploads
         and any per-item removals (checkboxes feeding remove_media[]) in a single Save. --}}
    <div class="lg:col-span-2 space-y-6">
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

        <x-card :title="__('Reference images')">
            @if ($referenceImages->isNotEmpty())
                <ul class="grid grid-cols-2 gap-2">
                    @foreach ($referenceImages as $image)
                        <li>
                            <img src="{{ $image->url() }}" alt="{{ $image->original_name }}" class="w-full rounded-md border border-gray-200 object-cover">
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
                <x-input-error :messages="$errors->get('reference_images.0')" class="mt-2" />
            </div>
        </x-card>

        <x-card :title="__('Reference files')">
            @if ($referenceFiles->isNotEmpty())
                <ul class="space-y-2">
                    @foreach ($referenceFiles as $file)
                        <li class="flex items-center justify-between gap-2 text-sm">
                            <a href="{{ $file->url() }}" class="truncate text-ocean-600 hover:text-ocean-800" download="{{ $file->original_name }}">{{ $file->original_name }}</a>
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
                <x-input-error :messages="$errors->get('reference_files.0')" class="mt-2" />
            </div>
        </x-card>
    </div>
</div>
