<x-app-layout>
    <x-page-heading>
        {{ __('New :label', ['label' => $type->label()]) }}
    </x-page-heading>

    {{-- The Create button lives in the sidebar Actions card, which the fields partial
         renders — it holds both columns of the form. --}}
    <form id="codex-entry-create-form" method="POST" action="{{ route('projects.codex.store', [$project, $type->routeKey()]) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        @include('codex.partials.fields')
    </form>
</x-app-layout>
