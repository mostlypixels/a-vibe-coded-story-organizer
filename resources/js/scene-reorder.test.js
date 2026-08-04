import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { moveScene, updateSceneMoveButtons } from './scene-reorder';

/**
 * Tests for `resources/js/scene-reorder.js` (continuous-numbering spec, task
 * 05). Builds the same shape story/index.blade.php renders for a chapter's
 * scenes — an `<article>` of sibling `<section>`s, each carrying its own
 * `data-scene-number` span and up/down move buttons — directly in jsdom,
 * bypassing Alpine and axios entirely (matching this codebase's existing
 * split of DOM-free/adapter logic from the framework glue, e.g.
 * navigation-guard.test.js).
 */

/** One chapter's worth of scenes. `numbers` gives each section's starting
 *  `data-scene-number` text, in DOM order. */
function buildArticle(numbers) {
    const article = document.createElement('article');

    numbers.forEach((number, index) => {
        const section = document.createElement('section');

        const numberSpan = document.createElement('span');
        numberSpan.setAttribute('data-scene-number', '');
        numberSpan.textContent = number;
        section.appendChild(numberSpan);

        const up = document.createElement('button');
        up.setAttribute('data-move', 'up');
        up.disabled = index === 0;
        section.appendChild(up);

        const down = document.createElement('button');
        down.setAttribute('data-move', 'down');
        down.disabled = index === numbers.length - 1;
        section.appendChild(down);

        article.appendChild(section);
    });

    document.body.appendChild(article);

    return article;
}

function numbersOf(article) {
    return Array.from(article.querySelectorAll('[data-scene-number]')).map((span) => span.textContent);
}

describe('moveScene', () => {
    afterEach(() => {
        vi.restoreAllMocks();
        delete window.axios;
        document.body.innerHTML = '';
    });

    it('swaps the two data-scene-number values along with the two sections, and leaves every other number alone', async () => {
        const article = buildArticle(['1.', '2.', '3.', '4.']);
        window.axios = { patch: vi.fn().mockResolvedValue({}) };

        const sections = article.querySelectorAll('section');
        const downButton = sections[1].querySelector('[data-move="down"]');

        await moveScene(downButton, '/scenes/2/move-down', 'down');

        // Scenes 2 and 3 swapped places...
        expect(Array.from(article.querySelectorAll('section'))).toEqual([sections[0], sections[2], sections[1], sections[3]]);
        // ...and their numbers swapped with them, so each section still shows
        // the number matching its new position. 1 and 4 never moved.
        expect(numbersOf(article)).toEqual(['1.', '2.', '3.', '4.']);
        expect(window.axios.patch).toHaveBeenCalledWith('/scenes/2/move-down');
    });

    it('does nothing when the PATCH fails: no section moves and no number changes', async () => {
        const article = buildArticle(['1.', '2.', '3.']);
        window.axios = { patch: vi.fn().mockRejectedValue(new Error('network error')) };

        const sections = article.querySelectorAll('section');
        const upButton = sections[1].querySelector('[data-move="up"]');

        await moveScene(upButton, '/scenes/2/move-up', 'up');

        expect(Array.from(article.querySelectorAll('section'))).toEqual(Array.from(sections));
        expect(numbersOf(article)).toEqual(['1.', '2.', '3.']);
    });

    it('does nothing when the button is already disabled (an end of the chapter)', async () => {
        const article = buildArticle(['1.', '2.']);
        window.axios = { patch: vi.fn() };

        const firstUp = article.querySelectorAll('section')[0].querySelector('[data-move="up"]');

        await moveScene(firstUp, '/scenes/1/move-up', 'up');

        expect(window.axios.patch).not.toHaveBeenCalled();
        expect(numbersOf(article)).toEqual(['1.', '2.']);
    });

    it('keeps the move buttons at the ends of the chapter disabled after a reorder', async () => {
        const article = buildArticle(['1.', '2.', '3.']);
        window.axios = { patch: vi.fn().mockResolvedValue({}) };

        // Move the first scene down — it is now in the middle, so its "up"
        // button must become enabled while the new first section's "up"
        // button (previously enabled) becomes disabled.
        const firstDown = article.querySelectorAll('section')[0].querySelector('[data-move="down"]');
        await moveScene(firstDown, '/scenes/1/move-down', 'down');

        const sections = article.querySelectorAll('section');
        expect(sections[0].querySelector('[data-move="up"]').disabled).toBe(true);
        expect(sections[0].querySelector('[data-move="down"]').disabled).toBe(false);
        expect(sections[1].querySelector('[data-move="up"]').disabled).toBe(false);
        expect(sections[2].querySelector('[data-move="down"]').disabled).toBe(true);
    });
});

describe('updateSceneMoveButtons', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('disables up on the first section and down on the last, leaving the middle enabled', () => {
        const article = buildArticle(['1.', '2.', '3.']);
        // Simulate every button starting enabled, as if freshly reordered.
        article.querySelectorAll('button').forEach((button) => { button.disabled = false; });

        updateSceneMoveButtons(article);

        const sections = article.querySelectorAll('section');
        expect(sections[0].querySelector('[data-move="up"]').disabled).toBe(true);
        expect(sections[0].querySelector('[data-move="down"]').disabled).toBe(false);
        expect(sections[1].querySelector('[data-move="up"]').disabled).toBe(false);
        expect(sections[1].querySelector('[data-move="down"]').disabled).toBe(false);
        expect(sections[2].querySelector('[data-move="up"]').disabled).toBe(false);
        expect(sections[2].querySelector('[data-move="down"]').disabled).toBe(true);
    });

    it('disables both buttons when there is only one section', () => {
        const article = buildArticle(['1.']);
        article.querySelectorAll('button').forEach((button) => { button.disabled = false; });

        updateSceneMoveButtons(article);

        const section = article.querySelector('section');
        expect(section.querySelector('[data-move="up"]').disabled).toBe(true);
        expect(section.querySelector('[data-move="down"]').disabled).toBe(true);
    });
});
