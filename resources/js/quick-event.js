/**
 * The "Save event" button inside `<x-single-event-field>`. It creates the
 * event through `projects.events.quick-store` and puts it into every event
 * picker on the page, so the writer answers the picker without leaving the
 * form.
 *
 * A progressive enhancement only: the parent form still carries
 * `new_*_title` / `new_*_datetime`, so a failed request leaves the typed
 * values in place and the page save creates the event instead. That is also
 * why a success must *clear* both inputs — a leftover title makes a second
 * event on the next page save.
 */

/** The two request keys, paired with the error slots that show their messages. */
const FIELDS = ['title', 'event_datetime'];

function fieldValues(root) {
    const dateField = root.querySelector('[data-quick-event-datetime]');

    return {
        titleInput: root.querySelector('[data-quick-event-title]'),
        dateField,
        // The date picker posts through one hidden `Y-m-d\TH:i` input.
        datetimeInput: dateField ? dateField.querySelector('input[type="hidden"]') : null,
    };
}

function showErrors(root, errors) {
    FIELDS.forEach((field) => {
        const slot = root.querySelector(`[data-quick-event-error="${field}"]`);

        if (!slot) return;

        slot.textContent = '';

        (errors[field] ?? []).forEach((message) => {
            const item = document.createElement('li');
            item.textContent = message;
            slot.appendChild(item);
        });
    });
}

function showStatus(root, message) {
    const status = root.querySelector('[data-quick-event-status]');

    if (status) status.textContent = message ?? '';
}

/**
 * Insert an option in the server's own `event_datetime, id` order. A tie
 * appends, because the new event holds the highest id.
 */
export function insertEventOption(select, event) {
    const option = document.createElement('option');

    option.value = String(event.id);
    option.textContent = event.label;
    option.dataset.datetime = event.datetime;

    const later = Array.from(select.options).find(
        (existing) => existing.dataset.datetime && existing.dataset.datetime > event.datetime
    );

    select.insertBefore(option, later ?? null);

    return option;
}

/**
 * Save the inline event of one picker. Resolves true when the event was
 * created, so the caller can collapse the section.
 */
export async function saveQuickEvent(root) {
    const { titleInput, dateField, datetimeInput } = fieldValues(root);

    showErrors(root, {});
    showStatus(root, '');

    let event;

    try {
        const response = await window.axios.post(root.dataset.quickEventUrl, {
            title: titleInput ? titleInput.value : '',
            event_datetime: datetimeInput ? datetimeInput.value : '',
        });

        event = response.data;
    } catch (error) {
        const response = error && error.response;

        showErrors(root, response && response.status === 422 ? response.data.errors ?? {} : {});
        showStatus(root, root.dataset.quickEventFailure);

        return false;
    }

    const owner = root.querySelector('select[data-event-picker]');

    document.querySelectorAll('select[data-event-picker]').forEach((select) => {
        const option = insertEventOption(select, event);

        if (select === owner) select.value = option.value;
    });

    if (titleInput) titleInput.value = '';
    if (datetimeInput) datetimeInput.value = '';
    // The visible year/month/day boxes hold their own state; the date field
    // resets them on this event.
    if (dateField) dateField.dispatchEvent(new CustomEvent('quick-event-clear'));

    showStatus(root, (root.dataset.quickEventSuccess ?? '').replace(':title', event.title));

    if (owner) {
        // Alpine re-enables the picker once the section collapses, one tick
        // later. Focus needs it enabled now.
        owner.disabled = false;
        owner.focus();
    }

    return true;
}
