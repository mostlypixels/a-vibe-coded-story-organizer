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

        <div class="flex items-center gap-2">
            {{-- Per-field indicator (ui.md "Inline indicator"): shows only this
                 field's own state, precise about which field is affected. Idle
                 renders nothing — no persistent chrome. --}}
            <span
                class="text-xs text-gray-500"
                data-autosave-indicator
                x-show="state !== 'idle'"
                style="display: none;"
                x-text="state"
            ></span>

            <a
                href="{{ $historyUrl }}"
                class="inline-flex items-center justify-center p-1 rounded-md text-navy-500 hover:bg-navy-50"
                title="{{ __('History') }}"
            >
                <span class="sr-only">{{ __('History') }}</span>
                <x-tabler-history class="h-4 w-4" />
            </a>
        </div>
    </div>

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
        <textarea
            id="{{ $field }}"
            name="{{ $field }}"
            rows="{{ $rows }}"
            data-hash="{{ $hash }}"
            form="{{ $form }}"
            class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm"
        >{{ $currentValue }}</textarea>
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

    <x-input-error :messages="$errors->get($field)" class="mt-2" />
</div>
