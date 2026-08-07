/**
 * Story overview scene reordering.
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
export function updateSceneMoveButtons(container) {
    const sections = container.querySelectorAll(':scope > section');

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
 * (numbers are project-wide), so a move swaps exactly
 * their two numbers and never touches any other row.
 *
 * The swap is on the two spans' *text*, not the DOM nodes carrying it. The
 * `data-scene-number` span already lives inside the `<section>` that moves,
 * so moving the sections (below) already carries each span along with it —
 * swapping the nodes too would cancel that move right back out and leave the
 * numbers exactly where they started. Swapping only the text is what makes
 * the visually-reordered sections show the right numbers.
 *
 * > [!WARNING]
 * > The container is the section's own parent, never a tag looked up by name.
 * > The scenes sit in a plain wrapper inside `x-collapsible-card`, and that
 * > card is free to change shape. `previousElementSibling` is already
 * > parent-relative, so the parent is the only element the swap can use.
 */
export async function moveScene(button, url, direction) {
    if (button.disabled) return;

    const section = button.closest('section');
    const container = section.parentElement;
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
        container.insertBefore(section, sibling);
    } else {
        container.insertBefore(sibling, section);
    }

    updateSceneMoveButtons(container);
}
