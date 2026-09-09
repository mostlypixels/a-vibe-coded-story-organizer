<x-app-layout>
    <x-page-heading>
        {{ __('Scenes referencing :name', ['name' => $entry->name]) }}
    </x-page-heading>

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <p class="text-content-muted">
                {{ __('Every scene that references :name.', ['name' => $entry->name]) }}
            </p>
            <a
                href="{{ route('codex.show', $entry) }}"
                class="text-sm text-content-muted hover:text-content shrink-0"
            >
                {{ __('← Back to :name', ['name' => $entry->name]) }}
            </a>
        </div>

        @if ($paginator->isEmpty())
            <div class="bg-surface-raised shadow-xs rounded-lg px-6 py-10 text-center text-content-muted">
                <p class="font-medium text-content-muted">{{ __('No scenes reference this entry yet.') }}</p>
            </div>
        @else
            <x-references.scene-table :scenes="$paginator" :show-book="$showBook" />

            <div class="flex flex-wrap items-center justify-between gap-4">
                <x-row-range :paginator="$paginator" />
                <div>{{ $paginator->links() }}</div>
            </div>
        @endif
    </div>
</x-app-layout>
