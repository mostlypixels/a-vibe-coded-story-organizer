/** Preview server-approved appearance values without saving them. */

/** Keep these properties aligned with `FontStyleBlock`. */
export const PREVIEW_PROPERTIES = {
    ui_font: '--font-sans',
    manuscript_font: '--font-manuscript',
    ui_scale: 'font-size',
    manuscript_scale: '--manuscript-scale',
    manuscript_leading: '--manuscript-leading',
    ui_leading: '--tw-leading',
};

export const BLOCK_FIELDS = ['theme_slug'];

const owns = (object, key) => Object.prototype.hasOwnProperty.call(object, key);

/** Reject values that are not in the server-provided maps. */
export function resolvePreview(maps, field, slug) {
    const options = maps?.[field];

    if (options === undefined || !owns(options, slug)) {
        return null;
    }

    if (BLOCK_FIELDS.includes(field)) {
        const declarations = options[slug];

        return declarations !== null && typeof declarations === 'object' ? declarations : null;
    }

    if (!owns(PREVIEW_PROPERTIES, field)) {
        return null;
    }

    return { [PREVIEW_PROPERTIES[field]]: options[slug] };
}

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
