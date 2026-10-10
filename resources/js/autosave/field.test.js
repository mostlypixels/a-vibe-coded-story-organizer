import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { DEBOUNCE_MS, fieldKeyFor, registerAutosaveField, shouldAutosave } from './field';
import { STATES } from './store';

function createAlpineStub() {
    const stores = {};
    const factories = {};

    return {
        store(name, value) {
            if (value !== undefined) {
                stores[name] = value;

                return undefined;
            }

            return stores[name];
        },
        data(name, factory) {
            factories[name] = factory;
        },
        factory(name) {
            return factories[name];
        },
    };
}

describe('fieldKeyFor', () => {
    it('keys a field as entity:id:field', () => {
        expect(fieldKeyFor({ entity: 'scene', id: 42, field: 'contents' })).toBe('scene:42:contents');
    });

    it('never collides two fields of the same entity', () => {
        const contents = fieldKeyFor({ entity: 'scene', id: 42, field: 'contents' });
        const summary = fieldKeyFor({ entity: 'scene', id: 42, field: 'summary' });

        expect(contents).not.toBe(summary);
    });
});

describe('shouldAutosave', () => {
    it('is false until the field has actually been edited', () => {
        expect(shouldAutosave(false, 42)).toBe(false);
    });

    it('is false on a create form even after an edit, since there is no id to PATCH', () => {
        expect(shouldAutosave(true, null)).toBe(false);
        expect(shouldAutosave(true, undefined)).toBe(false);
    });

    it('is true only once the field is dirty and belongs to an existing entity', () => {
        expect(shouldAutosave(true, 42)).toBe(true);
    });
});

/** An in-memory BroadcastChannel. A new class per test, so fields of old tests hear nothing. */
function fakeBroadcastChannel() {
    const members = new Set();

    return class FakeBroadcastChannel {
        constructor() {
            this.onmessage = null;
            members.add(this);
        }

        postMessage(data) {
            members.forEach((other) => other !== this && other.onmessage?.({ data }));
        }

        close() {
            members.delete(this);
        }
    };
}

describe('registerAutosaveField store dirty tracking', () => {
    let Alpine;

    beforeEach(() => {
        vi.stubGlobal('BroadcastChannel', fakeBroadcastChannel());
        Alpine = createAlpineStub();
        registerAutosaveField(Alpine);
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
        window.localStorage.clear();
        delete window.axios;
    });

    function mountField(config) {
        const root = document.createElement('div');
        const textarea = document.createElement('textarea');
        root.appendChild(textarea);
        document.body.appendChild(root);

        const field = Alpine.factory('autosaveField')(config);
        field.$root = root;
        field.$el = root;
        field.init();

        return { field, textarea };
    }

    it('label shows the translated badge text for the state, not the raw key', () => {
        const { field } = mountField({
            entity: 'scene',
            id: 42,
            field: 'contents',
            url: '/scenes/42',
            baseHash: 'abc',
            strings: { 'Session expired — your work is safe.': 'Session abgelaufen' },
        });

        field.setState(STATES.SESSION_EXPIRED);

        expect(field.label).toBe('Session abgelaufen');
    });

    it('isDirty() returns false and does not throw before any field has registered', () => {
        expect(Alpine.store('autosave').isDirty()).toBe(false);
    });

    it('typing in a field sets store.dirty[key] to true before the debounce timer fires', () => {
        vi.useFakeTimers();

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc' });

        textarea.value = 'hello';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        expect(Alpine.store('autosave').dirty[field.key]).toBe(true);
        expect(Alpine.store('autosave').isDirty()).toBe(true);
    });

    function mountEditorField(config) {
        const { field, textarea } = mountField(config);
        const editor = document.createElement('div');
        textarea.parentNode.appendChild(editor);

        const edit = (value) => {
            textarea.value = value;
            editor.dispatchEvent(new CustomEvent('wysiwyg:text-changed', { detail: { text: value }, bubbles: true }));
        };

        return { field, textarea, edit };
    }

    it('an edit made in the editor marks the field dirty, though it fires no input event', () => {
        vi.useFakeTimers();

        const { field, edit } = mountEditorField({ entity: 'scene', id: 42, field: 'description', url: '/scenes/42', baseHash: 'abc' });

        edit('<p>written in the editor</p>');

        expect(Alpine.store('autosave').dirty[field.key]).toBe(true);
        expect(Alpine.store('autosave').isDirty()).toBe(true);
    });

    it('clearing the editor autosaves the empty value, rather than leaving the old text stored', async () => {
        vi.useFakeTimers();
        window.axios = {
            patch: vi.fn().mockResolvedValue({ status: 200, headers: {}, data: { hash: 'new-hash' } }),
        };

        const { edit } = mountEditorField({ entity: 'scene', id: 42, field: 'description', url: '/scenes/42', baseHash: 'abc' });

        edit('<p>to be deleted</p>');
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);

        // Select-all then Delete. `syncTextarea()` maps an empty document to '', so
        // this is what the writer expects the server to store.
        edit('');
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);

        expect(window.axios.patch).toHaveBeenCalledTimes(2);
        expect(window.axios.patch.mock.calls[1][1]).toMatchObject({ value: '' });
    });

    it('destroy() stops the editor event autosaving into a torn-down field', () => {
        vi.useFakeTimers();
        window.axios = { patch: vi.fn() };

        const { field, edit } = mountEditorField({ entity: 'scene', id: 42, field: 'description', url: '/scenes/42', baseHash: 'abc' });

        field.destroy();
        edit('<p>after teardown</p>');
        vi.advanceTimersByTime(DEBOUNCE_MS);

        expect(window.axios.patch).not.toHaveBeenCalled();
    });

    it('a successful save clears store.dirty[key] back to false', async () => {
        window.axios = {
            patch: vi.fn().mockResolvedValue({ status: 200, headers: {}, data: { hash: 'new-hash' } }),
        };

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc' });

        textarea.value = 'hello';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        expect(Alpine.store('autosave').dirty[field.key]).toBe(true);

        await field.save({});

        expect(Alpine.store('autosave').dirty[field.key]).toBe(false);
        expect(Alpine.store('autosave').isDirty()).toBe(false);
    });

    it('typing while a save is in flight leaves the field dirty when the response lands', async () => {
        vi.useFakeTimers();

        let respond;
        window.axios = {
            patch: vi.fn(() => new Promise((resolve) => {
                respond = () => resolve({ status: 200, headers: {}, data: { hash: 'new-hash' } });
            })),
        };

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc' });

        textarea.value = 'first';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        // The PATCH is in flight, describing 'first'.
        const inFlight = field.save({});

        // The writer keeps typing before the response lands.
        textarea.value = 'first and second';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        respond();
        await inFlight;

        // The save succeeded — but for text the field no longer holds, so the
        // page still has unsaved changes and the leave-page warning must fire.
        expect(field.dirty).toBe(true);
        expect(Alpine.store('autosave').dirty[field.key]).toBe(true);
        expect(Alpine.store('autosave').isDirty()).toBe(true);

        // The hash still advances, or the pending save would 409 against a value
        // the server has already stored.
        expect(field.baseHash).toBe('new-hash');
    });

    /** A fake server that stores one value and answers 409 to a stale base_hash, like the real one. */
    function slowServer({ initialHash = 'h0' } = {}) {
        let storedHash = initialHash;
        let version = 0;
        const pending = [];

        const patch = vi.fn((url, body) => new Promise((resolve, reject) => {
            pending.push(() => {
                if (body.base_hash !== storedHash) {
                    reject({ response: { status: 409, headers: {} } });

                    return;
                }

                version += 1;
                storedHash = `h${version}`;
                resolve({ status: 200, headers: {}, data: { hash: storedHash } });
            });
        }));

        return {
            patch,
            respondNext: () => pending.shift()(),
            pendingCount: () => pending.length,
        };
    }

    it('typing during a slow save queues one follow-up with the new hash, not a false conflict', async () => {
        vi.useFakeTimers();
        const server = slowServer();
        window.axios = { patch: server.patch };

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'h0' });

        textarea.value = 'first';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
        expect(server.pendingCount()).toBe(1);

        // The first PATCH is still in flight when two more debounced saves fire.
        textarea.value = 'first and second';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
        textarea.value = 'first and second and third';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);

        expect(server.patch).toHaveBeenCalledTimes(1);

        server.respondNext();
        await vi.advanceTimersByTimeAsync(0);

        expect(server.patch).toHaveBeenCalledTimes(2);
        expect(server.patch.mock.calls[1][1]).toMatchObject({ value: 'first and second and third', base_hash: 'h1' });

        server.respondNext();
        await vi.advanceTimersByTimeAsync(0);

        expect(field.state).not.toBe('conflict');
        expect(field.state).toBe('saved');
        expect(field.dirty).toBe(false);
        expect(field.baseHash).toBe('h2');
    });

    it('flush() during a slow save resolves only after the queued follow-up lands', async () => {
        const server = slowServer();
        window.axios = { patch: server.patch };

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'h0' });

        textarea.value = 'first';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        field.flush({});

        textarea.value = 'first and more';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        let flushed = false;
        const second = field.flush({ runMatcher: true }).then(() => { flushed = true; });

        server.respondNext();
        await vi.waitFor(() => expect(server.patch).toHaveBeenCalledTimes(2));
        expect(flushed).toBe(false);
        expect(server.patch.mock.calls[1][1]).toMatchObject({ value: 'first and more', base_hash: 'h1', run_matcher: true });

        server.respondNext();
        await second;

        expect(field.state).toBe('saved');
        expect(field.dirty).toBe(false);
    });

    it('Ctrl-S during a slow save still runs the matcher when the text did not change (#199)', async () => {
        const server = slowServer();
        window.axios = { patch: server.patch };

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'h0' });

        textarea.value = 'first';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        field.flush({});

        // No more typing: only the matcher request is new.
        const second = field.flush({ runMatcher: true });

        server.respondNext();
        await vi.waitFor(() => expect(server.patch).toHaveBeenCalledTimes(2));
        expect(server.patch.mock.calls[1][1]).toMatchObject({ value: 'first', base_hash: 'h1', run_matcher: true });

        server.respondNext();
        await second;

        expect(field.state).toBe('saved');
        expect(field.dirty).toBe(false);
    });

    it('a new save cancels the pending retry, so only one retry chain runs', async () => {
        vi.useFakeTimers();
        const patch = vi.fn()
            .mockRejectedValueOnce(new Error('offline'))
            .mockResolvedValue({ status: 200, headers: {}, data: { hash: 'new-hash' } });
        window.axios = { patch };

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc' });

        textarea.value = 'hello';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        await field.save({});
        expect(field.state).toBe('retrying');

        await field.flush({});
        expect(patch).toHaveBeenCalledTimes(2);

        await vi.advanceTimersByTimeAsync(60_000);

        expect(patch).toHaveBeenCalledTimes(2);
    });

    it('destroy() cancels a pending retry', async () => {
        vi.useFakeTimers();
        const patch = vi.fn().mockRejectedValue(new Error('offline'));
        window.axios = { patch };

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc' });

        // No input event: this test is about the retry timer, not the debounce timer.
        textarea.value = 'hello';
        await field.save({});

        field.destroy();
        await vi.advanceTimersByTimeAsync(60_000);

        expect(patch).toHaveBeenCalledTimes(1);
    });

    it('a save whose text is unchanged on arrival still clears dirty', async () => {
        window.axios = {
            patch: vi.fn().mockResolvedValue({ status: 200, headers: {}, data: { hash: 'new-hash' } }),
        };

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc' });

        textarea.value = 'hello';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        await field.save({});

        expect(field.dirty).toBe(false);
        expect(Alpine.store('autosave').isDirty()).toBe(false);
    });

    it('a successful save dispatches word-count:reconcile on this field\'s [data-word-count] element, carrying the response word_count', async () => {
        window.axios = {
            patch: vi.fn().mockResolvedValue({ status: 200, headers: {}, data: { hash: 'new-hash', word_count: 7 } }),
        };

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc' });
        const counter = document.createElement('div');
        counter.setAttribute('data-word-count', '');
        field.$root.appendChild(counter);
        const handler = vi.fn();
        counter.addEventListener('word-count:reconcile', handler);

        textarea.value = 'hello';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        await field.save({});

        expect(handler).toHaveBeenCalledTimes(1);
        expect(handler.mock.calls[0][0].detail.wordCount).toBe(7);
    });

    it('a save on a field with no [data-word-count] element (e.g. this test file\'s other mounts) never throws', async () => {
        window.axios = {
            patch: vi.fn().mockResolvedValue({ status: 200, headers: {}, data: { hash: 'new-hash', word_count: 7 } }),
        };

        const { field, textarea } = mountField({ entity: 'scene', id: 43, field: 'contents', url: '/scenes/43', baseHash: 'abc' });

        textarea.value = 'hello';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        await expect(field.save({})).resolves.toBeUndefined();
    });

    it('destroy() removes the key from store.dirty entirely, mirroring fields/elements', () => {
        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc' });

        textarea.value = 'hello';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        expect(Alpine.store('autosave').dirty).toHaveProperty(field.key);

        field.destroy();

        expect(Alpine.store('autosave').dirty).not.toHaveProperty(field.key);
        expect(Alpine.store('autosave').fields).not.toHaveProperty(field.key);
        expect(Alpine.store('autosave').elements).not.toHaveProperty(field.key);
    });

    it('flush() resolves once the PATCH resolves', async () => {
        let respond;
        window.axios = {
            patch: vi.fn(() => new Promise((resolve) => {
                respond = () => resolve({ status: 200, headers: {}, data: { hash: 'new-hash' } });
            })),
        };

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc' });

        textarea.value = 'hello';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        const flushed = field.flush({});
        respond();

        await expect(flushed).resolves.toBeUndefined();
    });

    it('leaving the field runs the matcher', async () => {
        window.axios = {
            patch: vi.fn().mockResolvedValue({ status: 200, headers: {}, data: { hash: 'new-hash' } }),
        };

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc', matcher: true });

        textarea.value = 'Melchior';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        field.$root.dispatchEvent(new Event('focusout'));

        await vi.waitFor(() => expect(window.axios.patch).toHaveBeenCalledTimes(1));
        expect(window.axios.patch.mock.calls[0][1]).toMatchObject({ run_matcher: true });
    });

    it('Ctrl-S after a debounce save still runs the matcher once for the unmatched text', async () => {
        window.axios = {
            patch: vi.fn().mockResolvedValue({ status: 200, headers: {}, data: { hash: 'new-hash' } }),
        };

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc', matcher: true });

        textarea.value = 'Melchior';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        await field.save({});

        await field.flush({ runMatcher: true });
        await field.flush({ runMatcher: true });

        expect(window.axios.patch).toHaveBeenCalledTimes(2);
        expect(window.axios.patch.mock.calls[1][1]).toMatchObject({ value: 'Melchior', run_matcher: true });
    });

    it('a clean flush sends nothing on a field without the matcher', async () => {
        window.axios = {
            patch: vi.fn().mockResolvedValue({ status: 200, headers: {}, data: { hash: 'new-hash' } }),
        };

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'description', url: '/scenes/42', baseHash: 'abc' });

        textarea.value = 'Melchior';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        await field.save({});
        await field.flush({ runMatcher: true });

        expect(window.axios.patch).toHaveBeenCalledTimes(1);
    });

    it('a matcher response hands the references list to the page', async () => {
        window.axios = {
            patch: vi.fn().mockResolvedValue({
                status: 200,
                headers: {},
                data: { hash: 'new-hash', referenced_entries_html: '<ul><li>Melchior</li></ul>' },
            }),
        };
        const handler = vi.fn();
        window.addEventListener('codex-references-synced', handler);

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc', matcher: true });

        textarea.value = 'Melchior';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        await field.flush({ runMatcher: true });

        window.removeEventListener('codex-references-synced', handler);
        expect(handler).toHaveBeenCalledTimes(1);
        expect(handler.mock.calls[0][0].detail.html).toBe('<ul><li>Melchior</li></ul>');
    });

    it('flush() resolves, not undefined-by-accident, when the field is clean', async () => {
        window.axios = { patch: vi.fn() };

        const { field } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc' });

        await expect(field.flush({})).resolves.toBeUndefined();
        expect(window.axios.patch).not.toHaveBeenCalled();
    });

    it('store.flush(key) saves that field and resolves', async () => {
        let respond;
        window.axios = {
            patch: vi.fn(() => new Promise((resolve) => {
                respond = () => resolve({ status: 200, headers: {}, data: { hash: 'new-hash' } });
            })),
        };

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc' });

        textarea.value = 'hello';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        const flushed = Alpine.store('autosave').flush(field.key);
        respond();

        await flushed;

        expect(window.axios.patch).toHaveBeenCalledTimes(1);
        expect(Alpine.store('autosave').dirty[field.key]).toBe(false);
    });

    it('store.flush(key) resolves without error when the key is unknown', async () => {
        await expect(Alpine.store('autosave').flush('scene:999:contents')).resolves.toBeUndefined();
    });

    it('store.flush(key, { runMatcher: false }) posts run_matcher: false', async () => {
        window.axios = {
            patch: vi.fn().mockResolvedValue({ status: 200, headers: {}, data: { hash: 'new-hash' } }),
        };

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc' });

        textarea.value = 'hello';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        await Alpine.store('autosave').flush(field.key, { runMatcher: false });

        expect(window.axios.patch.mock.calls[0][1]).toMatchObject({ run_matcher: false });
    });

    it('isDirty() is true when any registered field is dirty and false once none are', async () => {
        window.axios = {
            patch: vi.fn().mockResolvedValue({ status: 200, headers: {}, data: { hash: 'new-hash' } }),
        };

        const first = mountField({ entity: 'scene', id: 1, field: 'contents', url: '/scenes/1', baseHash: 'a' });
        const second = mountField({ entity: 'scene', id: 2, field: 'contents', url: '/scenes/2', baseHash: 'b' });

        expect(Alpine.store('autosave').isDirty()).toBe(false);

        first.textarea.value = 'hello';
        first.textarea.dispatchEvent(new Event('input', { bubbles: true }));
        expect(Alpine.store('autosave').isDirty()).toBe(true);

        second.textarea.value = 'world';
        second.textarea.dispatchEvent(new Event('input', { bubbles: true }));
        expect(Alpine.store('autosave').isDirty()).toBe(true);

        await first.field.save({});
        expect(Alpine.store('autosave').isDirty()).toBe(true); // second field is still dirty

        await second.field.save({});
        expect(Alpine.store('autosave').isDirty()).toBe(false);
    });

    describe('two tabs on one field (#258)', () => {
        const scene = { entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'h0' };

        function type(textarea, value) {
            textarea.value = value;
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        }

        function conflictResponse(value, hash) {
            return { response: { status: 409, headers: {}, data: { value, hash } } };
        }

        it('a newer tab on the same field locks the older tab', () => {
            const older = mountField(scene);
            const newer = mountField(scene);

            expect(older.field.locked).toBe(true);
            expect(older.field.$root.inert).toBe(true);
            expect(newer.field.locked).toBe(false);
            expect(Alpine.store('autosave').worstState()).toBe(STATES.LOCKED);
        });

        it('a tab on another field stays unlocked', () => {
            const other = mountField({ ...scene, field: 'description' });
            mountField(scene);

            expect(other.field.locked).toBe(false);
        });

        it('a create form has no id and sends no claim', () => {
            const older = mountField(scene);
            mountField({ ...scene, id: null });

            expect(older.field.locked).toBe(false);
        });

        it('the older tab saves its pending text when it locks', () => {
            window.axios = { patch: vi.fn().mockResolvedValue({ status: 200, headers: {}, data: { hash: 'h1' } }) };
            const older = mountField(scene);
            type(older.textarea, 'unsaved words');

            mountField(scene);

            expect(window.axios.patch).toHaveBeenCalledWith('/scenes/42', expect.objectContaining({ value: 'unsaved words', base_hash: 'h0' }));
        });

        it('take over unlocks this tab and locks the other tab', () => {
            const older = mountField(scene);
            const newer = mountField(scene);

            older.field.takeOver();

            expect(older.field.locked).toBe(false);
            expect(older.field.$root.inert).toBe(false);
            expect(newer.field.locked).toBe(true);
        });

        it('a clean tab takes the text and hash that another tab saved', async () => {
            window.axios = { patch: vi.fn().mockResolvedValue({ status: 200, headers: {}, data: { value: 'new text', hash: 'h1', word_count: 2 } }) };
            const older = mountField(scene);
            const newer = mountField(scene);

            type(newer.textarea, 'new text');
            await newer.field.flush({});

            expect(older.textarea.value).toBe('new text');
            expect(older.field.baseHash).toBe('h1');
        });

        it('a 409 keeps the saved text and stops more saves until the writer chooses', async () => {
            window.axios = { patch: vi.fn().mockRejectedValue(conflictResponse('theirs', 'h9')) };
            const { field, textarea } = mountField(scene);

            type(textarea, 'mine');
            await field.flush({});
            type(textarea, 'mine and more');
            await field.flush({});

            expect(field.state).toBe(STATES.CONFLICT);
            expect(field.conflict).toEqual({ value: 'theirs', hash: 'h9' });
            expect(window.axios.patch).toHaveBeenCalledTimes(1);
        });

        it('keep mine saves this text over the newer text as a new revision', async () => {
            window.axios = {
                patch: vi.fn()
                    .mockRejectedValueOnce(conflictResponse('theirs', 'h9'))
                    .mockResolvedValueOnce({ status: 200, headers: {}, data: { value: 'mine', hash: 'h10' } }),
            };
            const { field, textarea } = mountField(scene);
            type(textarea, 'mine');
            await field.flush({});

            await field.keepMine();

            expect(window.axios.patch.mock.calls[1][1]).toMatchObject({ value: 'mine', base_hash: 'h9', new_revision: true });
            expect(field.conflict).toBeNull();
            expect(field.state).toBe(STATES.SAVED);
            expect(field.baseHash).toBe('h10');
        });

        it('load saved text first puts this text in history, then restores the saved text', async () => {
            window.axios = {
                patch: vi.fn()
                    .mockRejectedValueOnce(conflictResponse('theirs', 'h9'))
                    .mockResolvedValueOnce({ status: 200, headers: {}, data: { value: 'mine', hash: 'h10' } })
                    .mockResolvedValueOnce({ status: 200, headers: {}, data: { value: 'theirs', hash: 'h11' } }),
            };
            const { field, textarea } = mountField(scene);
            type(textarea, 'mine');
            await field.flush({});

            await field.loadSaved();

            expect(window.axios.patch.mock.calls[1][1]).toMatchObject({ value: 'mine', base_hash: 'h9', new_revision: true });
            expect(window.axios.patch.mock.calls[2][1]).toMatchObject({ value: 'theirs', base_hash: 'h10', new_revision: true });
            expect(textarea.value).toBe('theirs');
            expect(field.dirty).toBe(false);
            expect(field.baseHash).toBe('h11');
        });

        it('a page from a failed full-form save starts in the conflict, and saves nothing yet', async () => {
            vi.useFakeTimers();
            window.axios = { patch: vi.fn() };

            const { field } = mountField({ ...scene, dirty: true, conflict: { value: 'theirs', hash: 'h9' } });
            await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);

            expect(field.state).toBe(STATES.CONFLICT);
            expect(field.dirty).toBe(true);
            expect(Alpine.store('autosave').isDirty()).toBe(true);
            expect(window.axios.patch).not.toHaveBeenCalled();
        });

        it('a page that shows unsaved text autosaves it', async () => {
            vi.useFakeTimers();
            window.axios = { patch: vi.fn().mockResolvedValue({ status: 200, headers: {}, data: { hash: 'h1' } }) };

            const { textarea } = mountField({ ...scene, dirty: true });
            textarea.value = 'text from the failed save';
            await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);

            expect(window.axios.patch).toHaveBeenCalledWith('/scenes/42', expect.objectContaining({ value: 'text from the failed save' }));
        });

        it('flush() on a clean field waits for the running save', async () => {
            const server = slowServer();
            window.axios = { patch: server.patch };
            const { field, textarea } = mountField(scene);
            type(textarea, 'first');
            field.flush({});

            // The field is still dirty until the response, so mark it clean like a settled send would.
            let done = false;
            field.dirty = false;
            field.flush({}).then(() => {
                done = true;
            });
            await Promise.resolve();
            expect(done).toBe(false);

            server.respondNext();
            await vi.waitFor(() => expect(done).toBe(true));
            expect(field.baseHash).toBe('h1');
        });

        it('a failed first request asks again, so load saved text never turns into keep mine', async () => {
            vi.useFakeTimers();
            window.axios = {
                patch: vi.fn()
                    .mockRejectedValueOnce(conflictResponse('theirs', 'h9'))
                    .mockRejectedValueOnce({ response: { status: 500, headers: {} } }),
            };
            const { field, textarea } = mountField(scene);
            type(textarea, 'mine');
            await field.flush({});

            await field.loadSaved();
            await vi.advanceTimersByTimeAsync(60_000);

            expect(window.axios.patch).toHaveBeenCalledTimes(2);
            expect(field.state).toBe(STATES.CONFLICT);
            expect(field.conflict).toEqual({ value: 'theirs', hash: 'h9' });
            expect(textarea.value).toBe('mine');
        });

        it('a retry of the second request still asks for a new revision', async () => {
            vi.useFakeTimers();
            window.axios = {
                patch: vi.fn()
                    .mockRejectedValueOnce(conflictResponse('theirs', 'h9'))
                    .mockResolvedValueOnce({ status: 200, headers: {}, data: { value: 'mine', hash: 'h10' } })
                    .mockRejectedValueOnce({ response: { status: 500, headers: {} } })
                    .mockResolvedValueOnce({ status: 200, headers: {}, data: { value: 'theirs', hash: 'h11' } }),
            };
            const { field, textarea } = mountField(scene);
            type(textarea, 'mine');
            await field.flush({});

            await field.loadSaved();
            expect(field.dirty).toBe(true);
            await vi.advanceTimersByTimeAsync(60_000);

            expect(window.axios.patch.mock.calls[3][1]).toMatchObject({ value: 'theirs', base_hash: 'h10', new_revision: true });
            expect(field.dirty).toBe(false);
            expect(field.baseHash).toBe('h11');
        });
    });
});

describe('no localStorage writes', () => {
    let Alpine;

    beforeEach(() => {
        Alpine = createAlpineStub();
        registerAutosaveField(Alpine);
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
        window.localStorage.clear();
        delete window.axios;
    });

    function mountField(config) {
        const root = document.createElement('div');
        const textarea = document.createElement('textarea');
        root.appendChild(textarea);
        document.body.appendChild(root);

        const field = Alpine.factory('autosaveField')(config);
        field.$root = root;
        field.$el = root;
        field.init();

        return { field, textarea };
    }

    it('leaves localStorage empty when a dirty field receives beforeunload', () => {
        const { textarea } = mountField({ entity: 'scene', id: 1, field: 'contents', url: '/scenes/1', baseHash: 'abc' });

        textarea.value = 'unsaved edit';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        window.dispatchEvent(new Event('beforeunload'));

        expect(window.localStorage.length).toBe(0);
    });

    it('leaves localStorage empty when a dirty field saves successfully', async () => {
        window.axios = {
            patch: vi.fn().mockResolvedValue({ status: 200, headers: {}, data: { hash: 'new-hash' } }),
        };

        const { field, textarea } = mountField({ entity: 'scene', id: 2, field: 'contents', url: '/scenes/2', baseHash: 'abc' });

        textarea.value = 'hello';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        await field.save({});

        expect(field.dirty).toBe(false);
        expect(window.localStorage.length).toBe(0);
    });
});
