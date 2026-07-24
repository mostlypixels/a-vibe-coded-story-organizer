---
title: Autosave Storage Improvements — UI
---

# UI

## Draft recovery modal

Reuses `<x-dialog>` (`resources/views/components/dialog.blade.php`), the same
component `data-loss-warnings`' navigation-guard dialog and
`delete-with-move-dialog.blade.php` both already build on — no new modal primitive.

> [!WARNING]
> `data-loss-warnings`' resolution log recorded that `<x-dialog>` does **not** merge
> `$attributes` onto its inner `<x-modal>` root, so `x-data="draftRecoveryModal()"`
> placed directly on `<x-dialog>` is silently dropped and every `@click` inside it
> throws "not defined". Wrap the whole `<x-dialog>` block in a plain `<div
> x-data="draftRecoveryModal()">` instead, exactly as `layouts/app.blade.php`'s
> `unsaved-changes-guard` dialog already does — don't repeat that bug.

```blade
{{-- resources/views/components/autosave-draft-recovery-modal.blade.php (new) --}}
<div x-data="draftRecoveryModal()">
    <x-dialog name="draft-recovery" :title="__('Unsaved changes found')">
        <template x-for="entry in entries" :key="entry.key">
            <div class="border-b border-gray-200 py-3 last:border-b-0">
                <p class="text-sm text-gray-700" x-text="entry.label"></p>
                <p class="text-xs text-gray-500" x-text="entry.savedAtLabel"></p>

                {{-- Mirrors the per-field banner's own rule (handoff.md §9.7,
                     unchanged): a base-hash mismatch never offers a bare Restore. --}}
                <div class="mt-1 flex gap-3">
                    <button type="button" x-show="entry.action === 'restore'" x-on:click="restore(entry.key)" class="font-medium underline">
                        {{ __('Restore') }}
                    </button>
                    <a x-show="entry.action === 'compare-only'" :href="entry.compareUrl" class="font-medium underline">
                        {{ __('Compare') }}
                    </a>
                    <button type="button" x-on:click="discard(entry.key)" class="font-medium underline">
                        {{ __('Discard') }}
                    </button>
                </div>
            </div>
        </template>

        <x-slot name="footer">
            <x-button variant="secondary" x-on:click="discardAll()">{{ __('Discard all') }}</x-button>
            <x-button variant="primary" x-on:click="restoreAll()" x-show="entries.some(e => e.action === 'restore')">
                {{ __('Restore all') }}
            </x-button>
        </x-slot>
    </x-dialog>
</div>
```

Mounted once in `layouts/app.blade.php`, next to `<x-autosave-status-badge />` and the
existing `unsaved-changes-guard` dialog:

```blade
<x-autosave-status-badge />
<div x-data="navigationGuard()"> {{-- existing --}} … </div>
<x-autosave-draft-recovery-modal /> {{-- new --}}
```

`restoreAll()`/`discardAll()` iterate `entries` calling the same per-entry
`restore(key)`/`discard(key)` — no separate bulk code path, same actions just looped
(mirrors `delete-with-move-dialog`'s single-vs-bulk pattern of not duplicating logic
per button).

`entry.compareUrl` reuses `autosave-field.blade.php`'s existing `$compareUrl`
computation (`Route::has('revisions.compare')` guard) — `draft-recovery.js`'s
`collectDraftEntries()` needs each field's compare URL passed in from the store
alongside `elements`/`fields`, since that URL is currently only computed inside the
Blade component that's being removed from having its own recovery UI (§ `architecture.md`
§4's "no new storage-reading logic" note — this is the one piece of context that does
need to travel from Blade to the store, via a new `store.compareUrls[key]` populated
in `autosaveField()`'s `init()`, the same place `store.elements[key]` is already set).

## `autosave-field.blade.php` — removed, not replaced

The entire `data-autosave-draft-banner` block (current lines `82-117`) is deleted, not
adapted — the field component renders nothing for draft recovery anymore. The per-field
indicator (`data-autosave-indicator`, `:61-67`) and History link are untouched; only
the banner goes.

## Accessibility

* `<x-dialog>` already provides focus trapping and Esc-to-close (inherited from
  `<x-modal>`), consistent with `CLAUDE.md`'s keyboard-accessibility requirement — no
  new work needed here, same as the other two dialogs already shipped in this family.
* Esc/backdrop-close must not discard any draft (`overview.md`'s acceptance
  criterion) — verify this explicitly in testing, since `<x-modal>`'s default `close`
  handling only hides the dialog, it doesn't invoke any handler that could
  accidentally call `discardAll()`.
