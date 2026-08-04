/**
 * Story overview scene reordering (continuous-numbering spec, task 05).
 * Extracted out of `app.js` — the Vite entry, which also pulls in Alpine and
 * axios — purely so this logic can be unit tested per-module like every other
 * file in this directory.
 *
 * `story/index.blade.php` wires this up via a plain inline `onclick`, not an
 * Alpine component: the move buttons swap two adjacent `<section>`s and PATCH
 * the server, nothing reactive is bound to the result. `app.js` therefore
 * still assigns `window.moveScene = moveScene` for that inline handler to
 * find.
 */

/** Disables the up button on the first scene and the down button on the
 *  last, matching `:disabled="$loop->first"` / `:disabled="$loop->last"` at
 *  render time — re-run after every successful move since the ends change. */
export function updateSceneMoveButtons(article) {
    const sections = article.querySelectorAll(':scope > section');

    sections.forEach((section, index) => {
        const up = section.querySelector('[data-move="up"]');
        const down = section.querySelector('[data-move="down"]');

        if (up) up.disabled = index === 0;
        if (down) down.disabled = index === sections.length - 1;
    });
}

/**
 * Moves one scene's `<section>` up or down past its adjacent sibling and
 * swaps their two `data-scene-number` labels to match.
 *
 * Only the two numbers change: two scenes adjacent inside one chapter are
 * also adjacent in the project-wide continuous sequence
 * (`00-overview.md` — "numbers are project-wide"), so a move swaps exactly
 * their two numbers and never touches any other row.
 *
 * The swap is on the two spans' *text*, not the DOM nodes carrying it. The
 * `data-scene-number` span already lives inside the `<section>` that moves,
 * so moving the sections (below) already carries each span along with it —
 * swapping the nodes too would cancel that move right back out and leave the
 * numbers exactly where they started. Swapping only the text is what makes
 * the visually-reordered sections show the right numbers.
 */
export async function moveScene(button, url, direction) {
    if (button.disabled) return;

    const section = button.closest('section');
    const article = section.closest('article');
    const sibling = direction === 'up' ? section.previousElementSibling : section.nextElementSibling;

    if (!sibling || sibling.tagName !== 'SECTION') return;

    try {
        await window.axios.patch(url);
    } catch (e) {
        return;
    }

    const sectionNumber = section.querySelector('[data-scene-number]');
    const siblingNumber = sibling.querySelector('[data-scene-number]');

    if (sectionNumber && siblingNumber) {
        const sectionText = sectionNumber.textContent;
        sectionNumber.textContent = siblingNumber.textContent;
        siblingNumber.textContent = sectionText;
    }

    if (direction === 'up') {
        article.insertBefore(section, sibling);
    } else {
        article.insertBefore(sibling, section);
    }

    updateSceneMoveButtons(article);
}
