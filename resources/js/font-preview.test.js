import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { PREVIEW_PROPERTIES, registerFontPreview, resolvePreview } from './font-preview';

/** Same Alpine stand-in as resources/js/word-count.test.js. */
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

/** The lists admin/appearance/edit.blade.php renders into `x-data`, trimmed. */
const maps = {
    ui_font: { inter: 'Inter, sans-serif', georgia: 'Georgia, Times, serif' },
    manuscript_font: { inter: 'Inter, sans-serif', literata: 'Literata, serif' },
    ui_scale: { normal: '100%', large: '112.5%' },
    manuscript_scale: { same: '100%', larger: '112.5%' },
    manuscript_leading: { normal: '1.6', loose: '1.9' },
};

function mountFontPreview(Alpine) {
    const form = document.createElement('form');
    document.body.appendChild(form);

    const component = Alpine.factory('fontPreview')(maps);
    component.$root = form;
    component.init();

    return { component, form };
}

/** Adds a radio to the form and fires the `change` a real click would. */
function pick(form, name, value) {
    const input = document.createElement('input');
    input.type = 'radio';
    input.name = name;
    input.value = value;
    form.appendChild(input);
    input.checked = true;
    input.dispatchEvent(new Event('change', { bubbles: true }));

    return input;
}

describe('resolvePreview', () => {
    it('resolves a known slug to the authored value from the map', () => {
        expect(resolvePreview(maps, 'ui_font', 'georgia')).toEqual({
            property: '--font-sans',
            value: 'Georgia, Times, serif',
        });
    });

    it('is a no-op for an unknown slug', () => {
        expect(resolvePreview(maps, 'ui_font', 'comic-sans')).toBeNull();
    });

    it('is a no-op for an inherited Object key, not a lookup of the prototype', () => {
        expect(resolvePreview(maps, 'ui_font', 'constructor')).toBeNull();
        expect(resolvePreview(maps, 'ui_font', '__proto__')).toBeNull();
    });

    it('is a no-op for a field the preview does not drive, such as the theme radios', () => {
        expect(resolvePreview(maps, 'theme_slug', 'midnight')).toBeNull();
    });
});

describe('registerFontPreview', () => {
    let Alpine;

    beforeEach(() => {
        Alpine = createAlpineStub();
        registerFontPreview(Alpine);
    });

    afterEach(() => {
        document.body.innerHTML = '';
        document.documentElement.removeAttribute('style');
    });

    /** Each field writes its own property and only that one — a shared
     *  handler that wrote the wrong property would still "work" visually for
     *  one field, so every field is checked against a clean root. */
    it.each([
        ['ui_font', 'georgia', '--font-sans', 'Georgia, Times, serif'],
        ['manuscript_font', 'literata', '--font-manuscript', 'Literata, serif'],
        ['ui_scale', 'large', 'font-size', '112.5%'],
        ['manuscript_scale', 'larger', '--manuscript-scale', '112.5%'],
        ['manuscript_leading', 'loose', '--manuscript-leading', '1.9'],
    ])('%s writes %s only', (field, slug, property, value) => {
        const { form } = mountFontPreview(Alpine);

        pick(form, field, slug);

        const style = document.documentElement.style;
        expect(style.getPropertyValue(property).trim()).toBe(value);

        Object.values(PREVIEW_PROPERTIES)
            .filter((other) => other !== property)
            .forEach((other) => expect(style.getPropertyValue(other)).toBe(''));
    });

    it('writes nothing for an unknown slug and does not throw', () => {
        const { form } = mountFontPreview(Alpine);

        expect(() => pick(form, 'ui_font', 'comic-sans')).not.toThrow();

        expect(document.documentElement.getAttribute('style')).toBeNull();
    });

    it('ignores a change from anything that is not a radio', () => {
        const { form } = mountFontPreview(Alpine);
        const text = document.createElement('input');
        text.type = 'text';
        text.name = 'ui_font';
        text.value = 'georgia';
        form.appendChild(text);

        text.dispatchEvent(new Event('change', { bubbles: true }));

        expect(document.documentElement.getAttribute('style')).toBeNull();
    });

    it('destroy() removes the listener it added', () => {
        const { component, form } = mountFontPreview(Alpine);

        component.destroy();
        pick(form, 'ui_font', 'georgia');

        expect(document.documentElement.getAttribute('style')).toBeNull();
    });
});
