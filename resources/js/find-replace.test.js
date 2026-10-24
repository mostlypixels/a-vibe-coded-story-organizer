import { describe, expect, it, vi } from 'vitest';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { CONFIRM_ABOVE, FIND_REPLACE_COPY, fill, findReplace, registerFindReplace } from './find-replace';

const projectRoot = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', '..');

function mount(scenes = { 14: { hash: 'h14', indexes: [0, 1, 2] }, 15: { hash: 'h15', indexes: [0] } }, strings = {}) {
    const component = findReplace(scenes, strings);
    component.$refs = { selection: { value: '' }, form: { submit: vi.fn() } };

    return component;
}

function manyMatches(count) {
    return { 7: { hash: 'h7', indexes: Array.from({ length: count }, (_, i) => i) } };
}

describe('findReplace selection', () => {
    it('starts with every match selected', () => {
        const component = mount();

        expect(component.selectedCount()).toBe(4);
        expect(component.totalCount).toBe(4);
        expect(component.isSelected(14, 2)).toBe(true);
    });

    it('toggles one match', () => {
        const component = mount();

        component.toggle(14, 1);
        expect(component.isSelected(14, 1)).toBe(false);
        expect(component.selectedCount()).toBe(3);

        component.toggle(14, 1);
        expect(component.isSelected(14, 1)).toBe(true);
    });

    it('gives each scene a tri-state', () => {
        const component = mount();

        expect(component.sceneState(14)).toBe('all');
        component.toggle(14, 0);
        expect(component.sceneState(14)).toBe('some');
        component.toggle(14, 1);
        component.toggle(14, 2);
        expect(component.sceneState(14)).toBe('none');
    });

    it('selects a whole scene from some, and clears it from all', () => {
        const component = mount();

        component.toggle(14, 0);
        component.toggleScene(14);
        expect(component.sceneState(14)).toBe('all');

        component.toggleScene(14);
        expect(component.sceneState(14)).toBe('none');
        expect(component.sceneState(15)).toBe('all');
    });

    it('selects all and none across every scene', () => {
        const component = mount();

        component.selectNone();
        expect(component.selectedCount()).toBe(0);
        expect(component.sceneState(15)).toBe('none');

        component.selectAll();
        expect(component.selectedCount()).toBe(4);
        expect(component.sceneState(14)).toBe('all');
    });

    it('counts the selected matches and the scenes that hold them', () => {
        const component = mount();

        expect(component.counterText).toBe('4 of 4 matches selected in 2 scenes');

        component.toggleScene(15);
        expect(component.counterText).toBe('3 of 4 matches selected in 1 scene');
        expect(component.selectedCount(14)).toBe(3);
        expect(component.selectedCount(15)).toBe(0);
    });

    it('labels the replace button with the selected count', () => {
        const component = mount();

        expect(component.replaceLabel()).toBe('Replace 4 matches');
        expect(component.replaceLabel(15)).toBe('Replace 1 match');
    });

    it('uses the translation that Laravel supplied', () => {
        const component = mount(undefined, { 'Replace :count matches': 'Remplacer :count occurrences' });

        expect(component.replaceLabel()).toBe('Remplacer 4 occurrences');
    });

    it('builds the JSON shape that the apply request reads, with sorted indexes and no empty scene', () => {
        const component = mount();

        component.toggle(14, 0);
        component.toggle(14, 0);
        component.toggleScene(15);

        expect(component.payload()).toEqual({ 14: { hash: 'h14', indexes: [0, 1, 2] } });
        expect(Object.keys(component.payload())).toEqual(['14']);
    });

    it('sends only one scene for "Replace in this scene"', () => {
        const component = mount();

        component.request(15);

        expect(JSON.parse(component.$refs.selection.value)).toEqual({ 15: { hash: 'h15', indexes: [0] } });
        expect(component.$refs.form.submit).toHaveBeenCalledOnce();
    });

    it('sends nothing when nothing is selected', () => {
        const component = mount();
        component.selectNone();

        component.request();

        expect(component.$refs.form.submit).not.toHaveBeenCalled();
    });

    it('sends at once up to the confirm limit', () => {
        const component = mount(manyMatches(CONFIRM_ABOVE));

        component.request();

        expect(component.pending).toBeNull();
        expect(component.$refs.form.submit).toHaveBeenCalledOnce();
    });

    it('asks for confirmation above the limit, and sends on accept', () => {
        const component = mount(manyMatches(CONFIRM_ABOVE + 1));

        component.request();

        expect(component.$refs.form.submit).not.toHaveBeenCalled();
        expect(component.confirmText).toBe(`Replace ${CONFIRM_ABOVE + 1} matches in 1 scene?`);

        component.accept();

        expect(component.$refs.form.submit).toHaveBeenCalledOnce();
        expect(JSON.parse(component.$refs.selection.value)[7].indexes).toHaveLength(CONFIRM_ABOVE + 1);
    });

    it('sends nothing when the author cancels the confirmation', () => {
        const component = mount(manyMatches(CONFIRM_ABOVE + 1));

        component.request(7);
        expect(component.pending).toBe('7');

        component.cancel();
        component.accept();

        expect(component.pending).toBeNull();
        expect(component.$refs.form.submit).not.toHaveBeenCalled();
    });

    it('folds and unfolds every scene without a change to the selection', () => {
        const component = mount();
        const details = [{ open: false }, { open: true }];
        component.$root = { querySelectorAll: vi.fn(() => details) };

        component.setAllOpen(true);
        expect(details.every((d) => d.open)).toBe(true);

        component.setAllOpen(false);
        expect(details.some((d) => d.open)).toBe(false);
        expect(component.selectedCount()).toBe(4);
    });

    it('registers the Alpine component', () => {
        const Alpine = { data: vi.fn() };

        registerFindReplace(Alpine);

        expect(Alpine.data).toHaveBeenCalledWith('findReplace', findReplace);
    });
});

describe('fill', () => {
    it('replaces the named placeholders and keeps unknown ones', () => {
        expect(fill(':a of :b :c', { a: 1, b: 2 })).toBe('1 of 2 :c');
    });
});

describe('find and replace strings — every key has a Laravel translation', () => {
    it('ScriptTranslations.php lists every key', () => {
        const php = readFileSync(path.join(projectRoot, 'app/Support/ScriptTranslations.php'), 'utf8');

        for (const key of Object.values(FIND_REPLACE_COPY)) {
            expect(php.includes(`'${key}'`), key).toBe(true);
        }
    });
});
