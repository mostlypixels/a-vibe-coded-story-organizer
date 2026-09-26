import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { registerAutosaveSubmit } from './submit';
import { STATES } from './store';

// One document listener per registration: register once for the whole file.
const store = {
    fields: {},
    flushers: {},
    locked: {},
    isLocked() {
        return Object.values(this.locked).some(Boolean);
    },
    flushAll() {
        return Promise.all(Object.values(this.flushers).map((flusher) => flusher({})));
    },
};

registerAutosaveSubmit({
    store: () => store,
    nextTick: () => Promise.resolve(),
});

describe('registerAutosaveSubmit', () => {
    let form;
    let save;
    let submitted;

    beforeEach(() => {
        store.fields = { 'scene:1:contents': STATES.IDLE };
        store.flushers = { 'scene:1:contents': vi.fn().mockResolvedValue() };
        store.locked = {};

        form = document.createElement('form');
        save = document.createElement('button');
        save.type = 'submit';
        save.setAttribute('data-guard-save', '');
        form.appendChild(save);
        document.body.appendChild(form);

        // jsdom cannot navigate. Record each submit that gets through.
        submitted = vi.fn();
        form.addEventListener('submit', (event) => {
            if (!event.defaultPrevented) {
                submitted(event.submitter);
            }

            event.preventDefault();
        });
    });

    afterEach(() => {
        form.remove();
    });

    async function settle() {
        await new Promise((resolve) => setTimeout(resolve, 0));
    }

    it('waits for autosave, then submits with the same button', async () => {
        form.requestSubmit(save);

        expect(submitted).not.toHaveBeenCalled();
        expect(store.flushers['scene:1:contents']).toHaveBeenCalled();

        await settle();

        expect(submitted).toHaveBeenCalledTimes(1);
        expect(submitted).toHaveBeenCalledWith(save);
    });

    it('does not submit when autosave ends in a conflict', async () => {
        store.flushers['scene:1:contents'] = vi.fn(async () => {
            store.fields['scene:1:contents'] = STATES.CONFLICT;
        });

        form.requestSubmit(save);
        await settle();

        expect(submitted).not.toHaveBeenCalled();
    });

    it('does not submit from a locked tab', async () => {
        store.locked = { 'scene:1:contents': true };

        form.requestSubmit(save);
        await settle();

        expect(submitted).not.toHaveBeenCalled();
    });

    it('leaves a page without autosave fields alone', () => {
        store.flushers = {};

        form.requestSubmit(save);

        expect(submitted).toHaveBeenCalledTimes(1);
    });

    it('leaves a submit without a guarded save button alone', () => {
        const other = document.createElement('button');
        other.type = 'submit';
        form.appendChild(other);

        form.requestSubmit(other);

        expect(submitted).toHaveBeenCalledTimes(1);
        expect(store.flushers['scene:1:contents']).not.toHaveBeenCalled();
    });
});
