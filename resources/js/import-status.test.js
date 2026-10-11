import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { POLL_INTERVAL_MS, registerImportStatus } from './import-status';

function mount(reload = vi.fn()) {
    let factory;
    registerImportStatus({ data: (name, f) => (factory = f) }, reload);

    const component = factory('/imports/1/status', 'queued', 'Pending');
    component.init();

    return { component, reload };
}

function respondWith(body, ok = true) {
    globalThis.fetch.mockResolvedValue({ ok, json: async () => body });
}

describe('importStatus', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        globalThis.fetch = vi.fn();
    });

    afterEach(() => {
        vi.useRealTimers();
        delete globalThis.fetch;
    });

    it('fetches the status url every interval', async () => {
        respondWith({ state: 'running', label: 'Story imported', project_url: null });
        const { component } = mount();

        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS * 2);

        expect(globalThis.fetch).toHaveBeenCalledTimes(2);
        expect(globalThis.fetch).toHaveBeenCalledWith('/imports/1/status', expect.any(Object));
        expect(component.state).toBe('running');
        expect(component.label).toBe('Story imported');
    });

    it('stops and keeps the project url when the import completes', async () => {
        respondWith({ state: 'completed', label: 'Completed', project_url: '/projects/9' });
        const { component, reload } = mount();

        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS * 3);

        expect(globalThis.fetch).toHaveBeenCalledTimes(1);
        expect(component.state).toBe('completed');
        expect(component.projectUrl).toBe('/projects/9');
        expect(reload).not.toHaveBeenCalled();
    });

    it('stops and reloads the page when the import stalls', async () => {
        respondWith({ state: 'stalled', label: 'Story imported', project_url: null });
        const { reload } = mount();

        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS * 3);

        expect(globalThis.fetch).toHaveBeenCalledTimes(1);
        expect(reload).toHaveBeenCalledOnce();
    });

    it('keeps polling after a failed response', async () => {
        respondWith({}, false);
        const { component } = mount();

        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS * 2);

        expect(globalThis.fetch).toHaveBeenCalledTimes(2);
        expect(component.state).toBe('queued');
    });

    it('stops polling when Alpine destroys it', async () => {
        respondWith({ state: 'running', label: 'Pending', project_url: null });
        const { component } = mount();

        component.destroy();
        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS * 2);

        expect(globalThis.fetch).not.toHaveBeenCalled();
    });
});
