{{--
    The page-level draft recovery modal (autosave-storage-improvements task 03).
    Replaces the old inline per-field banner: once per page load, lists every
    localStorage draft still worth offering (non-expired, non-drop-silently) across
    every mounted <x-autosave-field>, with per-entry Restore/Compare/Discard plus a
    Restore all/Discard all shortcut. Mounted once, globally, in layouts/app.blade.php.

    x-data lives on this wrapping div, not directly on <x-dialog> — mirrors
    layouts/app.blade.php's existing unsaved-changes-guard mount. <x-dialog>'s own
    root markup (dialog.blade.php -> modal.blade.php) does not forward extra
    attributes onto its inner <x-modal>, so an x-data placed straight on <x-dialog>
    is silently dropped and every @click inside it would throw "not defined".
--}}
<div x-data="draftRecoveryModal()">
    <x-dialog name="draft-recovery" :title="__('Unsaved changes found')">
        <template x-for="entry in entries" :key="entry.key">
            <div class="border-b border-gray-200 py-3 last:border-b-0">
                <p class="text-sm text-gray-700" x-text="entry.key"></p>
                <p class="text-xs text-gray-500" x-text="new Date(entry.savedAt).toLocaleString()"></p>

                {{-- Mirrors the old per-field banner's own rule (still binding,
                     00-overview.md decision 6/handoff.md §9.7): a base-hash
                     mismatch never offers a bare Restore. --}}
                <div class="mt-1 flex gap-3">
                    <button
                        type="button"
                        x-show="entry.action === 'restore'"
                        style="display: none;"
                        x-on:click="restore(entry.key)"
                        class="font-medium underline"
                    >
                        {{ __('Restore') }}
                    </button>
                    <a
                        x-show="entry.action === 'compare-only'"
                        style="display: none;"
                        :href="entry.compareUrl"
                        class="font-medium underline"
                    >
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
            <x-button
                variant="primary"
                x-on:click="restoreAll()"
                x-show="entries.some((entry) => entry.action === 'restore')"
            >
                {{ __('Restore all') }}
            </x-button>
        </x-slot>
    </x-dialog>
</div>
