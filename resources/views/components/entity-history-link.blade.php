@props([
    'model',
])

@php
    use App\Support\AutosavableFields;

    // The entity-level entry point into the revisions browser: one link per
    // revisionable edit screen, pointing at that entity's *unfiltered* history
    // (the per-field History icon on x-autosave-field lands on the same page
    // with `?field=` set).
    //
    // The URL slug is derived from the model class rather than passed in by the
    // call site: seven screens hand-writing "act"/"codex"/… is seven chances to
    // typo a slug into a 404 nobody notices until a writer clicks it. Passing an
    // unregistered model throws here, loudly, at render time.
    $historyUrl = route('revisions.index', [
        'entity' => AutosavableFields::slugFor($model::class),
        'id' => $model->getKey(),
    ]);
@endphp

<x-button variant="secondary" :href="$historyUrl" icon="tabler-history" {{ $attributes }}>
    {{ __('History') }}
</x-button>
