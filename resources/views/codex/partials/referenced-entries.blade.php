{{-- Codex entries a scene's contents reference. Rendered by scenes/edit.blade.php and,
     as its own response HTML, by SceneCodexEntryController::store() — one template kept
     in step instead of a Blade/Alpine twin. --}}
@if ($referencedEntries->isEmpty())
    <p class="mt-2 text-sm text-content-muted">{{ __('No codex entries referenced yet.') }}</p>
@else
    <ul class="mt-2 space-y-1">
        @foreach ($referencedEntries as $entry)
            <li>
                <a href="{{ route('codex.show', $entry) }}" class="text-sm text-link hover:text-link-hover">
                    {{ $entry->name }}
                </a>
                <span class="text-xs text-content-subtle">({{ $entry->type->label() }})</span>
            </li>
        @endforeach
    </ul>
@endif
