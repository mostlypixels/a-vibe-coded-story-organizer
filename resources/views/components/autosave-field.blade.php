@props([
    'entity',
    'model',
    'field',
    'label' => null,
    'rows' => 4,
    'form' => null,
])

@php
    use App\Enums\FieldKind;
    use App\Support\AutosavableFields;
    use App\Support\WordCounter;
    use App\Support\WordCountFormat;

    $kind = AutosavableFields::kindOf($entity, $field);
    $currentValue = (string) ($model->{$field} ?? '');
    $hash = hash('sha256', $currentValue);
    $autosaveUrl = route('autosave.update', ['entity' => $entity, 'id' => $model->id, 'field' => $field]);

    $historyUrl = route('revisions.index', ['entity' => $entity, 'id' => $model->id, 'field' => $field]);

    $wordCount = $model instanceof \App\Models\Scene && $field === 'contents'
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
        initialValue: @js($currentValue),
    })"
    data-autosave-field="{{ $entity }}:{{ $model->id }}:{{ $field }}"
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
            >{{ $currentValue }}</x-textarea>
        @else
            <x-wysiwyg
                id="{{ $field }}"
                name="{{ $field }}"
                :value="$currentValue"
                :rows="$rows"
                :markdown="$kind === FieldKind::Markdown"
                :form="$form"
            />
        @endif

        <div class="mt-1 flex items-center gap-2">
            <span
                class="text-xs font-medium text-link"
                data-autosave-indicator
                x-show="state !== 'idle'"
                style="display: none;"
                x-text="state"
            ></span>

            <x-word-count :count="$wordCount" x-text="displayText()" aria-live="off" class="ml-auto" />
        </div>
    </div>

    <x-input-error :messages="$errors->get($field)" class="mt-2" />
</div>
