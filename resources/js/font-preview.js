/**
 * Live preview for the appearance form: picking a radio repaints the whole
 * page at once, because the same `:root` custom properties `FontStyleBlock`
 * writes server-side are re-written on `document.documentElement`.
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
};

/**
 * Pure lookup, exported for tests: returns `{ property, value }` for a known
 * field and slug, or `null` for anything else — an unknown field (the theme
 * radios go through the same listener), an unknown slug, or an inherited
 * Object key such as `constructor`.
 */
export function resolvePreview(maps, field, slug) {
    const property = PREVIEW_PROPERTIES[field];
    const options = maps?.[field];

    if (property === undefined || options === undefined) {
        return null;
    }

    if (!Object.prototype.hasOwnProperty.call(options, slug)) {
        return null;
    }

    return { property, value: options[slug] };
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

            document.documentElement.style.setProperty(resolved.property, resolved.value);
        },
    }));
}
