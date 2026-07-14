# UI — Alias references v1

## Codex edit page (`resources/views/codex/partials/fields.blade.php`)

1. **Help text under aliases** — extend the existing paragraph under the `x-string-list` aliases
   field (around line 38):

   ```blade
   <p class="text-sm text-gray-500">{{ __('Other names this entry is known by (optional).') }}</p>
   <p class="text-sm text-gray-500">{{ __('Scenes are scanned for these names automatically when saved. If aliases overlap with another entry\'s name or alias, matches can be ambiguous.') }}</p>
   ```

   (Two short `<p>` tags, or combine into one — match whatever reads better; no component needed
   for static help text elsewhere in this file.)

2. **"Referenced in scenes" sidebar card** — `codex/partials/fields.blade.php` currently only
   renders its `<x-slot:sidebar>` on the shared create/edit partial, with `$entry === null` on
   create. This card is **edit-only** (no scenes reference an entry that doesn't exist yet):

   ```blade
   @if ($entry)
       <x-card :title="__('Referenced in scenes')">
           @if ($referencingScenes->isEmpty())
               <p class="text-sm text-gray-500">{{ __('No scenes reference this entry yet.') }}</p>
           @else
               <ul class="space-y-2">
                   @foreach ($referencingScenes as $scene)
                       <li>
                           <a href="{{ route('scenes.edit', $scene) }}" class="text-sm text-ocean-600 hover:text-ocean-800">
                               {{ $scene->chapter->act->name }} &mdash; {{ $scene->chapter->name }} &mdash; {{ $scene->name }}
                           </a>
                           @if ($scene->event)
                               <span class="block text-xs text-gray-400">{{ $scene->event->title }} &mdash; {{ $scene->event->event_datetime->format('M j, Y') }}</span>
                           @endif
                       </li>
                   @endforeach
               </ul>
           @endif
       </x-card>
   @endif
   ```

   Placed inside the existing `<x-slot:sidebar>` block, after the Tags card. Reuses
   `scenes.edit` linking convention already established by `documentation`'s "Link list-view
   names and covers to their edit pages" work (recent commit `530a4d7`).

3. Controller passes `referencingScenes` from `CodexEntryController::edit` (see
   `architecture.md`); `codex.create` doesn't need the variable since the `@if ($entry)` guard
   skips it, but keep the controller/view contract explicit — pass an empty collection isn't
   needed since `create()` never renders this block.

## Scene edit page (`resources/views/scenes/edit.blade.php`)

Add a new sidebar card, inside the existing `<x-slot:sidebar>` (after "Share this scene", before
the `codex.partials.as-of` include — reference material before "as of" state, matching reading
order top-to-bottom: share link → what this scene references → codex state at this moment):

```blade
<x-card :title="__('Codex references')">
    <p class="text-sm text-gray-500">{{ __('Detected from the scene contents on last save.') }}</p>

    @if ($referencedEntries->isEmpty())
        <p class="mt-2 text-sm text-gray-500">{{ __('No codex entries referenced yet.') }}</p>
    @else
        <ul class="mt-2 space-y-1">
            @foreach ($referencedEntries as $entry)
                <li>
                    <a href="{{ route('codex.edit', $entry) }}" class="text-sm text-ocean-600 hover:text-ocean-800">
                        {{ $entry->name }}
                    </a>
                    <span class="text-xs text-gray-400">({{ $entry->type->label() }})</span>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
```

- The "Detected from the scene contents on last save" caption is the one piece of copy that
  encodes the "no AJAX, only updates on Save" behavior from the spec — without it, a writer who
  edits contents and doesn't see the sidebar update might think the feature is broken.
- No grouping-by-type component reuse from `codex.partials.as-of` here: that partial is built
  around per-attribute value resolution (a different shape). A flat list ordered by `type` then
  `name` (already sorted server-side, see `architecture.md`) is enough for v1 — flag in
  `open-questions.md` if the user wants the heavier grouped-card treatment instead.

## No new Blade components

Everything above reuses `x-card`, `x-string-list` (already there), and plain `<a>`/`<ul>` — no
new reusable component crosses the "used in 2+ places" bar (`CLAUDE.md`: don't add abstraction
before there is a second caller).
