import './bootstrap';

import Alpine from 'alpinejs';
import { registerWysiwyg } from './wysiwyg';
import { registerAutosaveField } from './autosave/field';
import { registerAutosaveBadge } from './autosave/badge';
import { registerDraftRecoveryModal } from './autosave/draft-recovery';
import { registerNavigationGuard } from './navigation-guard';

window.Alpine = Alpine;

registerWysiwyg(Alpine);
registerAutosaveField(Alpine);
registerAutosaveBadge(Alpine);
registerDraftRecoveryModal(Alpine);
registerNavigationGuard(Alpine);

Alpine.start();

function updateSceneMoveButtons(article) {
    const sections = article.querySelectorAll(':scope > section');

    sections.forEach((section, index) => {
        const up = section.querySelector('[data-move="up"]');
        const down = section.querySelector('[data-move="down"]');

        if (up) up.disabled = index === 0;
        if (down) down.disabled = index === sections.length - 1;
    });
}

window.moveScene = async function (button, url, direction) {
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

    if (direction === 'up') {
        article.insertBefore(section, sibling);
    } else {
        article.insertBefore(sibling, section);
    }

    updateSceneMoveButtons(article);
};
