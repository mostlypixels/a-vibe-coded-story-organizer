<x-app-layout>
    <x-page-heading>
        {{ __('Codex entries referenced by :name', ['name' => $scene->name]) }}
    </x-page-heading>

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <p class="text-content-muted">
                {{ __('Every codex entry that :name references.', ['name' => $scene->name]) }}
            </p>
            <a
                href="{{ route('scenes.show', $scene) }}"
                class="text-sm text-content-muted hover:text-content shrink-0"
            >
                {{ __('← Back to :name', ['name' => $scene->name]) }}
            </a>
        </div>

        @if ($paginator->isEmpty())
            <div class="bg-surface-raised shadow-xs rounded-lg px-6 py-10 text-center text-content-muted">
                <p class="font-medium text-content-muted">{{ __('This scene references no codex entries yet.') }}</p>
            </div>
        @else
            <x-references.entry-table :entries="$paginator" />

            <div class="flex flex-wrap items-center justify-between gap-4">
                <x-row-range :paginator="$paginator" />
                <div>{{ $paginator->links() }}</div>
            </div>
        @endif
    </div>
</x-app-layout>
