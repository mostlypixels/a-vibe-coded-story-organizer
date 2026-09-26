@props([
    'entity',
    'model',
    'field',
    'label' => null,
    'rows' => 4,
    'form' => null,
    'quickCodexEntry' => false,
])

@php
    use App\Enums\FieldKind;
    use App\Support\AutosavableFields;
    use App\Support\FieldHash;
    use App\Support\ScriptTranslations;
    use App\Support\WordCounter;
    use App\Support\WordCountFormat;

    $kind = AutosavableFields::kindOf($entity, $field);
    $currentValue = (string) ($model->{$field} ?? '');
    $hash = FieldHash::of($currentValue);

    // A failed full-form save comes back with the text of the writer. Show it, not the stored text.
    $shownValue = (string) old($field, $currentValue);
    $conflicted = $errors->has("base_hashes.$field");
    $autosaveUrl = route('autosave.update', ['entity' => $entity, 'id' => $model->id, 'field' => $field]);

    $historyUrl = route('revisions.index', ['entity' => $entity, 'id' => $model->id, 'field' => $field]);

    // Only scene contents feed the codex reference matcher.
    $isSceneContents = $model instanceof \App\Models\Scene && $field === 'contents';

    $wordCount = $isSceneContents
        ? $model->word_count
        : WordCounter::count($currentValue, $kind);
    $wordCountTemplates = WordCountFormat::jsTemplates();
@endphp

<div
    x-data="autosaveField({
        entity: @js($entity),
        id: {{ (int) $model->id }},
        field: @js($field),
        url: @js($autosaveUrl),
        baseHash: @js($hash),
        initialValue: @js($shownValue),
        dirty: @js($shownValue !== $currentValue),
        conflict: @js($conflicted ? ['value' => $currentValue, 'hash' => $hash] : null),
        matcher: @js($isSceneContents),
        strings: @js(ScriptTranslations::autosaveBadge()),
    })"
    data-autosave-field="{{ $entity }}:{{ $model->id }}:{{ $field }}"
    :class="{ 'opacity-60': locked }"
>
    <div class="flex items-center justify-between gap-2">
        <x-input-label for="{{ $field }}" :value="$label" />

        <a
            href="{{ $historyUrl }}"
            class="inline-flex items-center justify-center p-1 rounded-md text-link hover:bg-info-surface"
            title="{{ __('History') }}"
        >
            <span class="sr-only">{{ __('History') }}</span>
            <x-tabler-history class="h-4 w-4" />
        </a>
    </div>

    {{-- The full-form save compares this with the stored hash, so it cannot overwrite newer text. --}}
    <input type="hidden" name="base_hashes[{{ $field }}]" value="{{ $hash }}" :value="baseHash" @if ($form) form="{{ $form }}" @endif>

    {{-- This scope must contain the input because editor events bubble to it. --}}
    <div
        x-data="wordCount({
            initialCount: {{ (int) $wordCount }},
            templates: @js($wordCountTemplates),
        })"
        data-word-count
    >
        @if ($kind === FieldKind::Plain)
            <x-textarea
                id="{{ $field }}"
                name="{{ $field }}"
                rows="{{ $rows }}"
                form="{{ $form }}"
                class="mt-1 block w-full"
            >{{ $shownValue }}</x-textarea>
        @else
            <x-wysiwyg
                id="{{ $field }}"
                name="{{ $field }}"
                :value="$shownValue"
                :rows="$rows"
                :markdown="$kind === FieldKind::Markdown"
                :form="$form"
                :quick-codex-entry="$quickCodexEntry"
            />
        @endif

        <div class="mt-1 flex items-center gap-2">
            <span
                class="text-xs font-medium text-link"
                data-autosave-indicator
                x-show="state !== 'idle'"
                style="display: none;"
                x-text="label"
            ></span>

            <x-word-count :count="$wordCount" x-text="displayText()" aria-live="off" class="ml-auto" />
        </div>
    </div>

    <div
        x-show="conflict"
        style="display: none;"
        class="mt-2 rounded-md border border-danger bg-danger-surface p-3 text-sm text-danger-surface-content"
        role="alert"
        data-autosave-conflict
    >
        <p>{{ __('This text was changed in another tab or on another device.') }}</p>
        <p>{{ __('History keeps both versions.') }}</p>

        <div class="mt-2 flex flex-wrap gap-2">
            <x-button type="button" variant="secondary" size="sm" @click="keepMine()">{{ __('Keep mine') }}</x-button>
            <x-button type="button" variant="secondary" size="sm" @click="loadSaved()">{{ __('Load saved text') }}</x-button>
        </div>
    </div>

    <x-input-error :messages="$errors->get($field)" class="mt-2" />
</div>
