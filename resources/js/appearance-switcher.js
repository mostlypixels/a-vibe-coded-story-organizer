/**
 * The quick appearance switcher in the top bar.
 *
 * - `appearanceSwitcherLoader` fetches the panel HTML on the first open of its dropdown.
 * - `appearanceSwitcher` runs inside that panel: live preview, then one-field saves.
 *
 * CSS values come only from the server preview map, through `resolvePreview()`.
 */
import { OPEN_EVENT } from './dropdown';
import { applyDeclarations, resolvePreview } from './font-preview';

/**
 * The slug next to `current` in the ordered list, clamped at both ends.
 * Null when `current` is not in the list.
 */
export function neighbourSlug(steps, current, direction) {
    const index = Array.isArray(steps) ? steps.indexOf(current) : -1;

    if (index === -1) {
        return null;
    }

    const next = Math.min(Math.max(index + Math.sign(direction), 0), steps.length - 1);

    return steps[next];
}

/**
 * Sends one value at a time. A newer value for a field replaces its queued value,
 * so fast clicks send at most two requests per field, and never out of order.
 */
export function createSaveQueue(send) {
    const pending = new Map();
    let running = null;

    async function drain() {
        while (pending.size > 0) {
            const [field, value] = pending.entries().next().value;
            pending.delete(field);

            try {
                await send(field, value);
            } catch {
                // The sender reports its own failure. The next field still goes out.
            }
        }

        running = null;
    }

    return {
        push(field, value) {
            pending.set(field, value);
            running ??= drain();

            return running;
        },

        drop(field) {
            pending.delete(field);
        },

        idle() {
            return running ?? Promise.resolve();
        },
    };
}

/** Field => slug for every value the server has not confirmed yet. */
export function unsavedChanges(active, saved) {
    return Object.fromEntries(Object.entries(active).filter(([field, slug]) => saved[field] !== slug));
}

/** The token axios sends for Laravel's CSRF check. */
function xsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * The browser can cancel an axios request when the page unloads. A `keepalive` fetch
 * survives the unload, so a change made just before a link click still saves.
 */
export function sendOnLeave(url, changes) {
    if (Object.keys(changes).length === 0) return;

    fetch(url, {
        method: 'PATCH',
        keepalive: true,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(changes),
    }).catch(() => {});
}

/** Put the radios and selects named `field` back on `slug`. */
function syncControls(root, field, slug) {
    root?.querySelectorAll(`[name="${field}"]`).forEach((control) => {
        if (control.type === 'radio') {
            control.checked = control.value === slug;
        } else {
            control.value = slug;
        }
    });
}

export function registerAppearanceSwitcher(Alpine) {
    /**
     * Config: `url` (the panel route), `id` (the dropdown's disclosure id),
     * `messages.loading`, `messages.failed`. The panel HTML goes into `x-ref="body"`.
     */
    Alpine.data('appearanceSwitcherLoader', (config = {}) => {
        // Kept off the Alpine proxy: a promise is not view state.
        let request = null;

        return {
            loaded: false,
            status: config.messages?.loading ?? '',

            init() {
                this._onOpen = (event) => {
                    if (event.detail === config.id) this.load();
                };
                window.addEventListener(OPEN_EVENT, this._onOpen);
            },

            destroy() {
                window.removeEventListener(OPEN_EVENT, this._onOpen);
            },

            load() {
                if (this.loaded) return Promise.resolve();

                if (request) return request;

                this.status = config.messages?.loading ?? '';
                request = window.axios
                    .get(config.url)
                    .then((response) => {
                        this.$refs.body.innerHTML = response.data;
                        this.loaded = true;
                        this.status = '';
                    })
                    .catch(() => {
                        // `loaded` stays false, so the next open tries again.
                        this.status = config.messages?.failed ?? '';
                    })
                    .finally(() => {
                        request = null;
                    });

                return request;
            },
        };
    });

    /** Config: built by `AppearanceSwitcherController`. */
    Alpine.data('appearanceSwitcher', (config = {}) => {
        // Kept off the Alpine proxy, like the loader's request.
        const saved = { ...config.active };
        let queue = null;

        return {
            active: { ...config.active },
            labels: config.labels ?? {},
            error: '',

            init() {
                queue = createSaveQueue((field, slug) => this.save(field, slug));

                this._onLeave = () => sendOnLeave(config.url, unsavedChanges(this.active, saved));
                window.addEventListener('pagehide', this._onLeave);
            },

            destroy() {
                window.removeEventListener('pagehide', this._onLeave);
            },

            change(field, slug) {
                const declarations = resolvePreview(config.previewMap, field, slug);

                if (declarations === null) return;

                applyDeclarations(declarations);
                this.active[field] = slug;

                return queue.push(field, slug);
            },

            step(field, direction) {
                if (!this.canStep(field, direction)) return;

                return this.change(field, neighbourSlug(config.steps?.[field], this.active[field], direction));
            },

            canStep(field, direction) {
                const next = neighbourSlug(config.steps?.[field], this.active[field], direction);

                return next !== null && next !== this.active[field];
            },

            async save(field, slug) {
                try {
                    await window.axios.patch(config.url, { [field]: slug }, { headers: { Accept: 'application/json' } });
                } catch {
                    this.revert(field);

                    return;
                }

                saved[field] = slug;
                this.error = '';
            },

            /** The screen shows the stored value again, and a queued newer value is dropped with it. */
            revert(field) {
                queue.drop(field);

                const declarations = resolvePreview(config.previewMap, field, saved[field]);

                if (declarations !== null) applyDeclarations(declarations);

                this.active[field] = saved[field];
                syncControls(this.$root, field, saved[field]);
                this.error = config.messages?.failed ?? '';
            },
        };
    });
}
