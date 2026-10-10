<x-app-layout>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-heading level="1">{{ $note->title }}</x-heading>
            @if ($categoryPath)
                <p class="mt-1 text-sm text-content-muted">
                    <a href="{{ route('projects.notes.index', ['project' => $note->project_id, 'category' => $note->note_category_id]) }}" class="hover:text-link">{{ $categoryPath }}</a>
                </p>
            @endif
        </div>

        <div class="flex shrink-0 items-center gap-1">
            <x-icon-edit-link :href="route('notes.edit', $note)" />
            <x-icon-button as="a" icon="history" variant="outline-solid" :label="__('History')" href="{{ route('revisions.index', ['entity' => 'note', 'id' => $note->id]) }}" />
            <x-icon-delete-button :action="route('notes.destroy', $note)" :confirm="__('Are you sure you want to delete this note?')" />
        </div>
    </div>

    <div class="space-y-6">
        @if ($outline->hasContents())
            <x-card :title="__('Contents')" icon="tabler-list">
                <nav aria-label="{{ __('Contents') }}">
                    <ul class="space-y-1 text-sm">
                        @foreach ($outline->headings as $heading)
                            {{-- Literal classes keep Tailwind's JIT aware of each indent. --}}
                            <li @class([
                                'pl-0' => $heading['level'] - $outline->topLevel() === 0,
                                'pl-4' => $heading['level'] - $outline->topLevel() === 1,
                                'pl-8' => $heading['level'] - $outline->topLevel() === 2,
                                'pl-12' => $heading['level'] - $outline->topLevel() === 3,
                            ])>
                                <a href="#{{ $heading['anchor'] }}" class="hover:text-link">{{ $heading['text'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </x-card>
        @endif

        @if (filled($note->body))
            <x-card>
                <x-rich-text :html="$outline->html" />
            </x-card>
        @endif

        @if ($linkGroups !== [])
            <x-card :title="__('Linked to')" icon="tabler-link">
                @include('notes.partials.link-list', ['note' => $note, 'groups' => $linkGroups])
            </x-card>
        @endif
    </div>
</x-app-layout>
