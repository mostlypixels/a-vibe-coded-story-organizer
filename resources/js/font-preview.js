/**
 * Live preview for the appearance form: picking a radio repaints the whole
 * page at once, because the same `:root` custom properties `FontStyleBlock` and
 * `ThemeStyleBlock` write server-side are re-written on
 * `document.documentElement`. Fonts, sizes, spacing and the colour theme all
 * preview through this one listener.
 *
 * Progressive enhancement only. The form submits and persists identically with
 * JS off, and nothing here is saved: navigating away discards the preview and
 * the next page load paints the stored values again.
 *
 * > [!WARNING]
 * > A preview value is never read from the DOM and never assembled here. It is
 * > looked up in `maps` — the config lists the server rendered into `x-data` —
 * > by the radio's slug. A slug that is not a key of its own list writes
 * > nothing. This is the JS half of the rule that keeps user input out of CSS;
 * > reading `input.value` (or a `data-*` attribute) straight into
 * > `setProperty()` breaks it.
 */

/**
 * Radio field name -> the CSS property it drives, in one place because two
 * copies drift. The list matches what `App\Services\FontStyleBlock` emits:
 * change one side and the preview stops agreeing with the saved page.
 */
export const PREVIEW_PROPERTIES = {
    ui_font: '--font-sans',
    manuscript_font: '--font-manuscript',
    ui_scale: 'font-size',
    manuscript_scale: '--manuscript-scale',
    manuscript_leading: '--manuscript-leading',
    ui_leading: '--tw-leading',
};

/**
 * The fields whose map entry is a whole `property -> value` block instead of one
 * value. A theme moves every colour token at once, so `ThemeStyleBlock` sends the
 * same declarations it prints server-side and this file names no colour property.
 */
export const BLOCK_FIELDS = ['theme_slug'];

const owns = (object, key) => Object.prototype.hasOwnProperty.call(object, key);

/**
 * Pure lookup, exported for tests: returns the `property -> value` declarations a
 * known field and slug preview, or `null` for anything else — an unknown field, an
 * unknown slug, or an inherited Object key such as `constructor`.
 */
export function resolvePreview(maps, field, slug) {
    const options = maps?.[field];

    if (options === undefined || !owns(options, slug)) {
        return null;
    }

    if (BLOCK_FIELDS.includes(field)) {
        const declarations = options[slug];

        // A block arrives already keyed by property. Anything but an object means the
        // server sent a shape this file does not understand: paint nothing.
        return declarations !== null && typeof declarations === 'object' ? declarations : null;
    }

    if (!owns(PREVIEW_PROPERTIES, field)) {
        return null;
    }

    return { [PREVIEW_PROPERTIES[field]]: options[slug] };
}

/**
 * Alpine component on the appearance `<form>`. One delegated `change`
 * listener on the root, so the native radios keep their own arrow-key
 * navigation and no input needs an attribute of its own.
 */
export function registerFontPreview(Alpine) {
    Alpine.data('fontPreview', (maps = {}) => ({
        init() {
            this._onChange = (event) => this.apply(event.target);
            this.$root.addEventListener('change', this._onChange);
        },

        destroy() {
            this.$root.removeEventListener('change', this._onChange);
        },

        apply(input) {
            if (!input || input.type !== 'radio') {
                return;
            }

            const resolved = resolvePreview(maps, input.name, input.value);

            if (resolved === null) {
                return;
            }

            for (const [property, value] of Object.entries(resolved)) {
                document.documentElement.style.setProperty(property, value);
            }
        },
    }));
}
