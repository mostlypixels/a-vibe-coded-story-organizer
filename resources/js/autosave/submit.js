/**
 * Hold a full-form save until autosave settles.
 *
 * A click on Save blurs the field, and the blur starts an autosave. The form would
 * then send the old base hash, and the server would see a false conflict. So the
 * submit waits for every save, then sends the form again with the new hashes.
 * A conflict or a tab lock stops the submit: the page shows what to do.
 */

import { isGuardedSaveSubmit } from '../navigation-guard';
import { STATES } from './store';

export function registerAutosaveSubmit(Alpine) {
    let resubmitting = false;

    // Capture phase: this runs before the navigation guard sees the submit.
    document.addEventListener('submit', (event) => {
        const store = Alpine.store('autosave');

        if (resubmitting || !store || Object.keys(store.flushers).length === 0 || !isGuardedSaveSubmit(event)) {
            return;
        }

        event.preventDefault();

        const form = event.target;
        const submitter = event.submitter;

        store.flushAll()
            .then(() => Alpine.nextTick())
            .then(() => {
                if (store.isLocked() || Object.values(store.fields).includes(STATES.CONFLICT)) {
                    return;
                }

                resubmitting = true;

                try {
                    form.requestSubmit(submitter);
                } finally {
                    resubmitting = false;
                }
            });
    }, true);
}
