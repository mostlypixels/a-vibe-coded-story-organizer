/**
 * The page-level draft recovery module (`autosave-storage-improvements` task 02).
 * Replaces `autosave-field.blade.php`'s inline per-field banner (removed in task 03)
 * with one shared surface: a pure `collectDraftEntries()` that runs the existing
 * per-field triage logic once per registered field, and an
 * `Alpine.data('draftRecoveryModal', ...)` wrapper the page-level modal (task 03)
 * mounts once, globally.
 *
 * Mirrors `./badge.js`'s shape — pure lookups/logic exported separately from the
 * Alpine wrapper, so the DOM-free half is directly vitest-able without pulling in
 * the real Alpine runtime (`field.test.js`/`badge.test.js`'s existing convention).
 *
 * No new source of truth: `collectDraftEntries()` takes `Alpine.store('autosave')`'s
 * existing `fields`/`elements`/`compareUrls` maps as plain arguments and reuses
 * `readDraft()`/`isDraftExpired()`/`triageDraft()` unchanged — it never duplicates
 * the per-field draft-reading logic `field.js`'s old `checkForDraft()` used to have,
 * before task 03 removed it (that per-field triage now only happens here, once
 * globally, instead of once per field's own `init()`).
 */
import { clearDraft, readDraft } from './field';
import { isDraftExpired, triageDraft } from './store';

/**
 * Given the store's `fields`/`elements`/`compareUrls` maps (populated by every
 * mounted `autosaveField` component's `init()`), returns one entry per key with a
 * live (non-expired, non-`drop-silently`) draft.
 *
 * Expiry is checked *before* `triageDraft()` runs (00-overview.md decision 6): an
 * expired draft is skipped outright, exactly like "no draft", and never reaches the
 * three-way triage — regardless of what that triage would otherwise have decided.
 *
 * The "current server value" `triageDraft()` compares against is read from the
 * field's own textarea — at the point this runs (page load, before any restore),
 * that DOM node still holds the server-rendered initial value and its
 * `data-hash` attribute (set by `autosave-field.blade.php`), the exact same hash
 * the PATCH endpoint itself is the sole authority for (00-overview.md/handoff.md
 * §9.13) — no client-computed hash, no new Blade prop.
 */
export function collectDraftEntries(fields, elements, compareUrls) {
    const entries = [];

    for (const key of Object.keys(fields)) {
        const draft = readDraft(key);

        if (!draft || isDraftExpired(draft)) {
            continue;
        }

        const element = elements[key];
        const textarea = element ? element.querySelector('textarea') : null;

        // No live DOM node to compare against (shouldn't happen for a currently
        // registered field, but guards against a stale/mismatched map entry rather
        // than throwing while building the recovery list).
        if (!textarea) {
            continue;
        }

        const server = { value: textarea.value, hash: textarea.dataset.hash };
        const triage = triageDraft(draft, server);

        if (triage === 'drop-silently') {
            continue;
        }

        entries.push({
            key,
            action: triage === 'offer-restore' ? 'restore' : 'compare-only',
            value: draft.value,
            savedAt: draft.savedAt,
            compareUrl: compareUrls[key] ?? null,
        });
    }

    return entries;
}

export function registerDraftRecoveryModal(Alpine) {
    Alpine.data('draftRecoveryModal', () => ({
        entries: [],

        init() {
            const store = Alpine.store('autosave');

            this.entries = collectDraftEntries(store.fields, store.elements, store.compareUrls);

            if (this.entries.length > 0) {
                // Alpine walks the DOM top-down initializing directives, so at this
                // point (this component's own init()) the nested <x-dialog>/<x-modal>
                // this wraps hasn't had its own x-on:open-modal.window listener wired
                // up yet — dispatching synchronously here is a silent no-op the modal
                // never sees. $nextTick() defers to after Alpine finishes initializing
                // the whole tree, once that listener actually exists.
                this.$nextTick(() => this.$dispatch('open-modal', 'draft-recovery'));
            }
        },

        /**
         * Mirrors `field.js`'s current (pre-task-03) `restoreDraft()` mechanic
         * exactly, just invoked from outside the field's own component instance:
         * write the draft value into the field's real `<textarea>` and dispatch a
         * bubbling `input` event so the mounted `autosaveField` (and, once inside
         * `<x-wysiwyg>`, Tiptap's own hydration) picks it up the same way a
         * keystroke would.
         */
        restore(key) {
            const entry = this.entries.find((candidate) => candidate.key === key);
            const element = Alpine.store('autosave').elements[key];
            const textarea = element ? element.querySelector('textarea') : null;

            if (entry && textarea) {
                textarea.value = entry.value;
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
            }

            this.discard(key);
        },

        /**
         * Closing the modal (Esc/backdrop) never implicitly discards
         * (00-overview.md decision 8) — only this explicit action, called from a
         * Discard/Restore click, ever clears a draft from `localStorage`.
         */
        discard(key) {
            clearDraft(key);
            this.entries = this.entries.filter((entry) => entry.key !== key);
        },

        restoreAll() {
            // Snapshot the keys first — restore()/discard() both mutate `entries`
            // as they go, which would otherwise skip every other array element
            // while iterating the live array directly.
            for (const key of this.entries.map((entry) => entry.key)) {
                this.restore(key);
            }
        },

        discardAll() {
            for (const key of this.entries.map((entry) => entry.key)) {
                this.discard(key);
            }
        },
    }));
}
