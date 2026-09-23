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
 * Move adjacent sections and exchange their continuous-number labels.
 *
 * A failed request, for example after the session expires, shows the translated
 * message that Blade renders hidden in the section.
 */
export async function moveScene(button, url, direction) {
    if (button.disabled) return;

    const section = button.closest('section');
    const container = section.parentElement;
    const sibling = direction === 'up' ? section.previousElementSibling : section.nextElementSibling;
    const failure = section.querySelector('[data-move-error]');

    if (!sibling || sibling.tagName !== 'SECTION') return;

    try {
        await window.axios.patch(url);
    } catch (error) {
        if (failure) failure.hidden = false;
        console.error(error);

        return;
    }

    if (failure) failure.hidden = true;

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
