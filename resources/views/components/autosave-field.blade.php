@props([
    'entity',
    'model',
    'field',
    'label' => null,
    'rows' => 4,
    // Only needed when this field is rendered outside its owning <form> tag (e.g.
    // projects/edit.blade.php's "Book metadata"/"front & back matter" cards, which
    // sit below the closed </form> and associate every input via HTML5's `form=`
    // attribute instead). Forwarded onto the actual input so the field still
    // submits with the right form on a full-page Save, exactly like the textarea
    // it replaces did.
    'form' => null,
])

@php
    use App\Enums\FieldKind;
    use App\Support\AutosavableFields;
    use App\Support\WordCounter;
    use App\Support\WordCountFormat;

    // Kind, character cap, and coalescing window come from the registry, never
    // passed as props — "a future field is one registry line + one blade line"
    // (ui.md's "x-autosave-field component"). An unregistered $field throws here
    // (AutosavableFields::kindOf()'s array access), the same as it 404s server-side.
    $kind = AutosavableFields::kindOf($entity, $field);
    $currentValue = (string) ($model->{$field} ?? '');
    // The server is the sole hash authority (00-overview.md/handoff.md §9.13) even
    // for the very first render: this is the same hash() call the PATCH endpoint
    // uses, so base_hash starts correct without an extra round trip.
    $hash = hash('sha256', $currentValue);
    $autosaveUrl = route('autosave.update', ['entity' => $entity, 'id' => $model->id, 'field' => $field]);

    // Links to this field's revision history and compare views (the History icon
    // below, and the compare URL handed to the Alpine component for its own use).
    $historyUrl = route('revisions.index', ['entity' => $entity, 'id' => $model->id, 'field' => $field]);
    $compareUrl = route('revisions.compare', ['entity' => $entity, 'id' => $model->id, 'field' => $field]);

    // The live counter's starting number (word-count spec, task 7). Computed the
    // same way as the stored `scenes.word_count` — Scene.contents just happens to
    // already have that number cached on the model (Scene::booted()'s saving
    // hook), everything else is counted fresh from what's actually on screen —
    // so the very first paint is exact, not an estimate the writer has to wait
    // out. `WordCounter::count()` is safe on every kind, including the plain
    // `rights` field it was not originally written for (Plain is just a no-op
    // render step in its match arm).
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
        compareUrl: @js($compareUrl),
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

    {{-- Wraps the editor and the row below it in the live word counter's own
         Alpine scope (word-count spec, task 7). It has to enclose the
         editor/textarea, not just the counter `<span>`: `wysiwyg.js` dispatches
         its `wysiwyg:text-changed` CustomEvent from inside `<x-wysiwyg>`, and a
         plain `<textarea>`'s native `input` event only reaches a listener on an
         *ancestor* element — both rely on bubbling up to this div.
         `data-word-count` is also how field.js's `save()` finds this element
         (`$root.querySelector`) to deliver the authoritative count on save,
         mirroring how `fieldValue()` reaches the textarea the same way. --}}
    <div
        x-data="wordCount({
            initialCount: {{ (int) $wordCount }},
            templates: @js($wordCountTemplates),
        })"
        data-word-count
    >
        @if ($kind === FieldKind::Plain)
            {{-- The one kind that is a bare <textarea>, not <x-wysiwyg> — Project.rights
                 stays a plain textarea, never forced into the rich editor. --}}
            {{--
                `form="{{ $form }}"` renders empty (form="") when $form is null — per the
                HTML living standard, a form attribute that doesn't resolve to a real
                <form> id is treated exactly like the attribute being absent (falls back
                to the nearest ancestor <form>), so this is safe to always emit rather
                than needing a conditional that would break the tag's attribute parsing.
            --}}
            <x-textarea
                id="{{ $field }}"
                name="{{ $field }}"
                rows="{{ $rows }}"
                data-hash="{{ $hash }}"
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
                data-hash="{{ $hash }}"
                :form="$form"
            />
        @endif

        <div class="mt-1 flex items-center gap-2">
            {{-- Per-field autosave indicator (ui.md "Inline indicator"): shows
                 only this field's own state, precise about which field is
                 affected. Idle renders nothing — no persistent chrome. `state`
                 resolves up to the outer `autosaveField` scope (Alpine merges
                 every ancestor `x-data`, this nested one doesn't shadow it).
                 Bottom-left, distinct in weight and colour from the counter
                 opposite it — this reports whether work is *safe*, the counter
                 reports how much there *is*; the two must never read as one
                 thing (open-questions.md Q5 / architecture.md's "not a save
                 indicator"). --}}
            <span
                class="text-xs font-medium text-link"
                data-autosave-indicator
                x-show="state !== 'idle'"
                style="display: none;"
                x-text="state"
            ></span>

            {{-- `ml-auto`, not `justify-between` on the row above: at idle the
                 badge above is `display:none` (removed from flex layout, not
                 just hidden), which would leave a lone flex child and collapse
                 `justify-between` to the left — pinning the counter right with
                 its own margin keeps it in place regardless of the badge's
                 visibility. --}}
            <x-word-count :count="$wordCount" x-text="displayText()" aria-live="off" class="ml-auto" />
        </div>
    </div>

    <x-input-error :messages="$errors->get($field)" class="mt-2" />
</div>
