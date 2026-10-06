import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { OPEN_EVENT } from './dropdown';
import { createSaveQueue, neighbourSlug, registerAppearanceSwitcher, unsavedChanges } from './appearance-switcher';

function createAlpineStub() {
    const factories = {};

    return {
        data(name, factory) {
            factories[name] = factory;
        },
        factory(name) {
            return factories[name];
        },
    };
}

/** A promise the test settles by hand, to hold a request in flight. */
function deferred() {
    let resolve;
    let reject;
    const promise = new Promise((res, rej) => {
        resolve = res;
        reject = rej;
    });

    return { promise, resolve, reject };
}

const CONFIG = {
    url: '/admin/appearance',
    previewMap: {
        theme_slug: {
            midnight: { '--color-surface': '#101018' },
            daylight: { '--color-surface': '#ffffff' },
        },
        ui_font: { inter: 'Inter, sans-serif', georgia: 'Georgia, serif' },
        manuscript_scale: { smaller: '90%', same: '100%', larger: '112.5%' },
        ui_leading: { tight: '1.3', normal: '1.5' },
    },
    steps: {
        manuscript_scale: ['smaller', 'same', 'larger'],
        ui_leading: ['tight', 'normal'],
    },
    labels: {
        manuscript_scale: { smaller: '0.9×', same: '1×', larger: '1.125×' },
    },
    active: {
        theme_slug: 'daylight',
        ui_font: 'inter',
        manuscript_scale: 'same',
        ui_leading: 'normal',
    },
    messages: { failed: 'Could not save. Try again.' },
};

const property = (name) => document.documentElement.style.getPropertyValue(name).trim();

describe('neighbourSlug', () => {
    const steps = ['smaller', 'same', 'larger'];

    it('returns the next and the previous slug', () => {
        expect(neighbourSlug(steps, 'same', 1)).toBe('larger');
        expect(neighbourSlug(steps, 'same', -1)).toBe('smaller');
    });

    it('clamps at both ends', () => {
        expect(neighbourSlug(steps, 'larger', 1)).toBe('larger');
        expect(neighbourSlug(steps, 'smaller', -1)).toBe('smaller');
    });

    it('returns null for an unknown current slug', () => {
        expect(neighbourSlug(steps, 'huge', 1)).toBeNull();
        expect(neighbourSlug(undefined, 'same', 1)).toBeNull();
    });
});

describe('createSaveQueue', () => {
    it('keeps one request in flight and replaces the queued value of a field', async () => {
        const first = deferred();
        const send = vi.fn().mockReturnValueOnce(first.promise).mockResolvedValue();
        const queue = createSaveQueue(send);

        queue.push('ui_scale', 'large');
        queue.push('ui_scale', 'larger');
        queue.push('ui_scale', 'largest');

        expect(send).toHaveBeenCalledTimes(1);

        first.resolve();
        await queue.idle();

        expect(send.mock.calls).toEqual([
            ['ui_scale', 'large'],
            ['ui_scale', 'largest'],
        ]);
    });

    it('goes on with the next field after a failed send', async () => {
        const send = vi.fn().mockRejectedValueOnce(new Error('offline')).mockResolvedValue();
        const queue = createSaveQueue(send);

        queue.push('ui_font', 'georgia');
        queue.push('theme_slug', 'midnight');
        await queue.idle();

        expect(send).toHaveBeenCalledTimes(2);
    });
});

describe('unsavedChanges', () => {
    it('lists only the fields that differ from the saved value', () => {
        expect(unsavedChanges({ ui_font: 'georgia', ui_scale: 'normal' }, { ui_font: 'inter', ui_scale: 'normal' }))
            .toEqual({ ui_font: 'georgia' });
        expect(unsavedChanges({ ui_font: 'inter' }, { ui_font: 'inter' })).toEqual({});
    });
});

describe('appearanceSwitcher', () => {
    let Alpine;

    beforeEach(() => {
        Alpine = createAlpineStub();
        registerAppearanceSwitcher(Alpine);
        window.axios = { patch: vi.fn().mockResolvedValue({ status: 204 }) };
    });

    // A component left mounted keeps its pagehide listener and would leak into later tests.
    let mounted = [];

    afterEach(() => {
        mounted.forEach((component) => component.destroy());
        mounted = [];
        document.body.innerHTML = '';
        document.documentElement.removeAttribute('style');
        delete window.axios;
    });

    function mount(config = CONFIG) {
        const root = document.createElement('div');
        root.innerHTML = `
            <input type="radio" name="theme_slug" value="midnight">
            <input type="radio" name="theme_slug" value="daylight" checked>
            <select name="ui_font"><option value="inter" selected>Inter</option><option value="georgia">Georgia</option></select>
        `;
        document.body.appendChild(root);

        const component = Alpine.factory('appearanceSwitcher')(config);
        component.$root = root;
        component.init();
        mounted.push(component);

        return { component, root };
    }

    it('previews and saves one field with a JSON request', async () => {
        const { component } = mount();

        await component.change('theme_slug', 'midnight');

        expect(property('--color-surface')).toBe('#101018');
        expect(component.active.theme_slug).toBe('midnight');
        expect(window.axios.patch).toHaveBeenCalledExactlyOnceWith(
            '/admin/appearance',
            { theme_slug: 'midnight' },
            { headers: { Accept: 'application/json' } },
        );
    });

    it('applies nothing and sends nothing for an unknown slug or field', async () => {
        const { component } = mount();

        await component.change('ui_font', 'comic-sans');
        await component.change('container_width', 'wide');

        expect(document.documentElement.getAttribute('style')).toBeNull();
        expect(component.active.ui_font).toBe('inter');
        expect(window.axios.patch).not.toHaveBeenCalled();
    });

    it('sends two quick changes in order and lets a third replace the queued second', async () => {
        const first = deferred();
        window.axios.patch.mockReturnValueOnce(first.promise);
        const { component } = mount();

        component.step('manuscript_scale', -1);
        component.step('manuscript_scale', 1);
        component.step('manuscript_scale', 1);

        expect(window.axios.patch).toHaveBeenCalledTimes(1);
        expect(component.active.manuscript_scale).toBe('larger');
        expect(property('--manuscript-scale')).toBe('112.5%');

        first.resolve({ status: 204 });
        await component.change('ui_font', 'georgia');

        expect(window.axios.patch.mock.calls.map((call) => call[1])).toEqual([
            { manuscript_scale: 'smaller' },
            { manuscript_scale: 'larger' },
            { ui_font: 'georgia' },
        ]);
    });

    it('sends a change for each of two fields', async () => {
        const { component } = mount();

        component.change('ui_font', 'georgia');
        await component.change('theme_slug', 'midnight');

        expect(window.axios.patch.mock.calls.map((call) => call[1])).toEqual([
            { ui_font: 'georgia' },
            { theme_slug: 'midnight' },
        ]);
    });

    it('clamps a step at the end of the list and sends nothing', async () => {
        const { component } = mount();

        expect(component.canStep('ui_leading', 1)).toBe(false);
        expect(component.canStep('ui_leading', -1)).toBe(true);
        expect(component.canStep('ui_scale', 1)).toBe(false);

        await component.step('ui_leading', 1);

        expect(window.axios.patch).not.toHaveBeenCalled();
    });

    it('re-applies the last saved slug and shows the error when a save fails', async () => {
        window.axios.patch.mockRejectedValueOnce(new Error('offline'));
        const { component, root } = mount();
        root.querySelector('[value="midnight"]').checked = true;

        await component.change('theme_slug', 'midnight');

        expect(property('--color-surface')).toBe('#ffffff');
        expect(component.active.theme_slug).toBe('daylight');
        expect(root.querySelector('[value="daylight"]').checked).toBe(true);
        expect(component.error).toBe('Could not save. Try again.');
    });

    it('puts the select back after a failed font save', async () => {
        window.axios.patch.mockRejectedValueOnce(new Error('offline'));
        const { component, root } = mount();
        root.querySelector('select').value = 'georgia';

        await component.change('ui_font', 'georgia');

        expect(root.querySelector('select').value).toBe('inter');
        expect(property('--font-sans')).toBe('Inter, sans-serif');
    });

    describe('on page leave', () => {
        beforeEach(() => {
            window.fetch = vi.fn().mockResolvedValue({ status: 204 });
            document.cookie = 'XSRF-TOKEN=abc%3D';
        });

        afterEach(() => {
            delete window.fetch;
            document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT';
        });

        it('sends every unconfirmed change in one keepalive request', () => {
            const first = deferred();
            window.axios.patch.mockReturnValueOnce(first.promise);
            const { component } = mount();

            component.step('manuscript_scale', 1);
            component.change('ui_font', 'georgia');
            window.dispatchEvent(new Event('pagehide'));

            expect(window.fetch).toHaveBeenCalledOnce();
            const [url, options] = window.fetch.mock.calls[0];
            expect(url).toBe('/admin/appearance');
            expect(options.method).toBe('PATCH');
            expect(options.keepalive).toBe(true);
            expect(options.headers['X-XSRF-TOKEN']).toBe('abc=');
            expect(JSON.parse(options.body)).toEqual({ manuscript_scale: 'larger', ui_font: 'georgia' });
        });

        it('sends nothing when every change is saved', async () => {
            const { component } = mount();

            await component.change('theme_slug', 'midnight');
            window.dispatchEvent(new Event('pagehide'));

            expect(window.fetch).not.toHaveBeenCalled();
        });
    });

    it('clears the error on the next successful save', async () => {
        window.axios.patch.mockRejectedValueOnce(new Error('offline'));
        const { component } = mount();

        await component.change('theme_slug', 'midnight');
        await component.change('theme_slug', 'midnight');

        expect(component.error).toBe('');
        expect(component.active.theme_slug).toBe('midnight');
    });
});

describe('appearanceSwitcherLoader', () => {
    const LOADER = {
        url: '/admin/appearance/switcher',
        id: 'appearance-menu',
        messages: { loading: 'Loading…', failed: 'Could not load.' },
    };

    let Alpine;

    beforeEach(() => {
        Alpine = createAlpineStub();
        registerAppearanceSwitcher(Alpine);
        window.axios = { get: vi.fn().mockResolvedValue({ data: '<p data-panel>Panel</p>' }) };
    });

    afterEach(() => {
        document.body.innerHTML = '';
        delete window.axios;
    });

    function mount() {
        const body = document.createElement('div');
        document.body.appendChild(body);

        const component = Alpine.factory('appearanceSwitcherLoader')(LOADER);
        component.$refs = { body };
        component.init();

        return { component, body };
    }

    const open = (id = LOADER.id) => window.dispatchEvent(new CustomEvent(OPEN_EVENT, { detail: id }));

    it('fetches the panel on the first open only', async () => {
        const { component, body } = mount();

        open();
        await component.load();
        open();
        await component.load();

        expect(window.axios.get).toHaveBeenCalledExactlyOnceWith('/admin/appearance/switcher');
        expect(body.querySelector('[data-panel]')).not.toBeNull();
        expect(component.loaded).toBe(true);
        expect(component.status).toBe('');

        component.destroy();
    });

    it('ignores the opening of another dropdown', () => {
        const { component } = mount();

        open('account-menu');

        expect(window.axios.get).not.toHaveBeenCalled();

        component.destroy();
    });

    it('shows the failure text and retries on the next open', async () => {
        window.axios.get.mockRejectedValueOnce(new Error('offline'));
        const { component, body } = mount();

        await component.load();

        expect(component.loaded).toBe(false);
        expect(component.status).toBe('Could not load.');

        await component.load();

        expect(window.axios.get).toHaveBeenCalledTimes(2);
        expect(component.loaded).toBe(true);
        expect(body.querySelector('[data-panel]')).not.toBeNull();
    });
});
