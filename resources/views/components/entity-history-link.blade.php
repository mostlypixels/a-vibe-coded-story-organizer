@props([
    'model',
])

@php
    use App\Support\AutosavableFields;

    $historyUrl = route('revisions.index', [
        'entity' => AutosavableFields::slugFor($model::class),
        'id' => $model->getKey(),
    ]);
@endphp

<x-button variant="secondary" :href="$historyUrl" icon="tabler-history" {{ $attributes }}>
    {{ __('History') }}
</x-button>
