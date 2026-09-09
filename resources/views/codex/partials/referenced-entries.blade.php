{{-- Codex entries a scene's contents reference. Rendered by scenes/edit.blade.php,
     scenes/show.blade.php and, as its own response HTML, by
     SceneCodexEntryController::store() — one template kept in step instead of a
     Blade/Alpine twin.

     > [!WARNING]
     > The cap belongs here, not in the caller. The store() fragment replaces this
     > list in the editor; a cap outside the partial lets that list grow past it. --}}
@if ($referencedEntries->isEmpty())
    <p class="mt-2 text-sm text-content-muted">{{ __('No codex entries referenced yet.') }}</p>
@else
    <div class="mt-2">
        <x-references.entry-table
            :entries="$referencedEntries->take(config('search.cap'))"
            :see-all-route="$referencedEntries->count() > config('search.cap') ? route('scenes.codex-references.index', $scene) : null"
            :see-all-count="$referencedEntries->count()"
        />
    </div>
@endif
