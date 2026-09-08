import { afterEach, describe, expect, it, vi } from 'vitest';
import { saveQuickEvent } from './quick-event';

/** One `<x-single-event-field>` as it renders, without Alpine. */
function buildPicker(name, options = []) {
    const root = document.createElement('div');
    root.dataset.quickEventUrl = '/projects/1/events/quick';
    root.dataset.quickEventSuccess = 'Saved to the timeline: :title';
    root.dataset.quickEventFailure = 'The event was not saved.';

    const select = document.createElement('select');
    select.setAttribute('data-event-picker', '');
    select.name = name;

    const empty = document.createElement('option');
    empty.value = '';
    select.appendChild(empty);

    options.forEach(({ id, label, datetime }) => {
        const option = document.createElement('option');
        option.value = String(id);
        option.textContent = label;
        option.dataset.datetime = datetime;
        select.appendChild(option);
    });

    root.appendChild(select);

    const title = document.createElement('input');
    title.setAttribute('data-quick-event-title', '');
    root.appendChild(title);

    const dateField = document.createElement('div');
    dateField.setAttribute('data-quick-event-datetime', '');
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    dateField.appendChild(hidden);
    root.appendChild(dateField);

    ['title', 'event_datetime'].forEach((field) => {
        const slot = document.createElement('ul');
        slot.setAttribute('data-quick-event-error', field);
        root.appendChild(slot);
    });

    const status = document.createElement('p');
    status.setAttribute('data-quick-event-status', '');
    root.appendChild(status);

    document.body.appendChild(root);

    return { root, select, title, dateField, hidden, status };
}

const created = {
    id: 42,
    title: 'The Second Curse',
    datetime: '1215-04-02T00:00',
    label: 'The Second Curse — 2 April 1215',
};

function optionIds(select) {
    return Array.from(select.options).map((option) => option.value);
}

describe('saveQuickEvent', () => {
    afterEach(() => {
        vi.restoreAllMocks();
        delete window.axios;
        document.body.innerHTML = '';
    });

    it('inserts the option once into every picker on the page, in date order', async () => {
        const born = buildPicker('inception_event_id', [
            { id: 7, label: 'Dawn', datetime: '1200-01-01T00:00' },
            { id: 9, label: 'Dusk', datetime: '1300-01-01T00:00' },
        ]);
        const died = buildPicker('termination_event_id', [
            { id: 7, label: 'Dawn', datetime: '1200-01-01T00:00' },
            { id: 9, label: 'Dusk', datetime: '1300-01-01T00:00' },
        ]);
        window.axios = { post: vi.fn().mockResolvedValue({ data: created }) };

        await saveQuickEvent(born.root);

        // Between Dawn and Dusk, not appended after both.
        expect(optionIds(born.select)).toEqual(['', '7', '42', '9']);
        expect(optionIds(died.select)).toEqual(['', '7', '42', '9']);
        expect(born.select.options[2].textContent).toBe(created.label);
        expect(born.select.options[2].dataset.datetime).toBe(created.datetime);
    });

    it('appends after an event on the same instant, matching the server order by id', async () => {
        const picker = buildPicker('event_id', [
            { id: 7, label: 'Same day', datetime: '1215-04-02T00:00' },
        ]);
        window.axios = { post: vi.fn().mockResolvedValue({ data: created }) };

        await saveQuickEvent(picker.root);

        expect(optionIds(picker.select)).toEqual(['', '7', '42']);
    });

    it('selects the new event only in the picker that owns the button', async () => {
        const born = buildPicker('inception_event_id');
        const died = buildPicker('termination_event_id');
        window.axios = { post: vi.fn().mockResolvedValue({ data: created }) };

        await saveQuickEvent(born.root);

        expect(born.select.value).toBe('42');
        // Creating from Born must leave Died usable and unanswered.
        expect(died.select.value).toBe('');
    });

    it('clears both inputs on success, so the parent form does not make a second event', async () => {
        const picker = buildPicker('event_id');
        picker.title.value = 'The Second Curse';
        picker.hidden.value = '1215-04-02T00:00';
        window.axios = { post: vi.fn().mockResolvedValue({ data: created }) };

        const cleared = vi.fn();
        picker.dateField.addEventListener('quick-event-clear', cleared);

        const saved = await saveQuickEvent(picker.root);

        expect(saved).toBe(true);
        expect(picker.title.value).toBe('');
        expect(picker.hidden.value).toBe('');
        // The visible date boxes hold their own state and reset on this event.
        expect(cleared).toHaveBeenCalledOnce();
        expect(picker.status.textContent).toBe('Saved to the timeline: The Second Curse');
    });

    it('posts the two typed values', async () => {
        const picker = buildPicker('event_id');
        picker.title.value = 'The Second Curse';
        picker.hidden.value = '1215-04-02T00:00';
        window.axios = { post: vi.fn().mockResolvedValue({ data: created }) };

        await saveQuickEvent(picker.root);

        expect(window.axios.post).toHaveBeenCalledWith('/projects/1/events/quick', {
            title: 'The Second Curse',
            event_datetime: '1215-04-02T00:00',
        });
    });

    it('writes the 422 messages under the inputs and keeps the section usable', async () => {
        const picker = buildPicker('event_id');
        picker.title.value = '';
        picker.hidden.value = '1900-01-01T00:00';
        window.axios = {
            post: vi.fn().mockRejectedValue({
                response: {
                    status: 422,
                    data: {
                        errors: {
                            title: ['The title field is required.'],
                            event_datetime: ['The date must be inside your story window.'],
                        },
                    },
                },
            }),
        };

        const saved = await saveQuickEvent(picker.root);

        expect(saved).toBe(false);
        const messages = Array.from(picker.root.querySelectorAll('[data-quick-event-error] li')).map(
            (item) => item.textContent
        );
        expect(messages).toEqual([
            'The title field is required.',
            'The date must be inside your story window.',
        ]);
        expect(optionIds(picker.select)).toEqual(['']);
        expect(picker.hidden.value).toBe('1900-01-01T00:00');
    });

    it('leaves the typed values in place after a network failure, so the page save still works', async () => {
        const picker = buildPicker('event_id');
        picker.title.value = 'The Second Curse';
        picker.hidden.value = '1215-04-02T00:00';
        window.axios = { post: vi.fn().mockRejectedValue(new Error('Network Error')) };

        const saved = await saveQuickEvent(picker.root);

        expect(saved).toBe(false);
        expect(picker.title.value).toBe('The Second Curse');
        expect(picker.hidden.value).toBe('1215-04-02T00:00');
        expect(picker.status.textContent).toBe('The event was not saved.');
        expect(optionIds(picker.select)).toEqual(['']);
    });

    it('clears a previous error before a retry', async () => {
        const picker = buildPicker('event_id');
        window.axios = {
            post: vi
                .fn()
                .mockRejectedValueOnce({
                    response: { status: 422, data: { errors: { title: ['The title field is required.'] } } },
                })
                .mockResolvedValueOnce({ data: created }),
        };

        await saveQuickEvent(picker.root);
        await saveQuickEvent(picker.root);

        expect(picker.root.querySelectorAll('[data-quick-event-error] li')).toHaveLength(0);
    });
});
