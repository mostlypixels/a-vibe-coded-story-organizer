<div class="space-y-3">
    @if ($note->category)
        <x-badge>{{ $note->category->name }}</x-badge>
    @endif

    <x-rich-text :html="$note->body" :project="$project" />
</div>
