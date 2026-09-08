import './bootstrap';

import Alpine from 'alpinejs';
import { registerWysiwyg } from './wysiwyg';
import { registerAutosaveField } from './autosave/field';
import { registerAutosaveBadge } from './autosave/badge';
import { registerNavigationGuard } from './navigation-guard';
import { registerRevisionPicker } from './revision-picker';
import { registerWordCount } from './word-count';
import { registerChallengeChart, registerWordCountChart } from './word-count-chart';
import { registerFontPreview } from './font-preview';
import { registerSettingTrack } from './setting-track';
import { registerDateField } from './date-field';
import { moveScene } from './scene-reorder';
import { saveQuickEvent } from './quick-event';
import { registerQuickCodexEntry } from './quick-codex-entry';

window.Alpine = Alpine;

registerWysiwyg(Alpine);
registerAutosaveField(Alpine);
registerAutosaveBadge(Alpine);
registerNavigationGuard(Alpine);
registerRevisionPicker(Alpine);
registerWordCount(Alpine);
registerWordCountChart(Alpine);
registerChallengeChart(Alpine);
registerFontPreview(Alpine);
registerSettingTrack(Alpine);
registerDateField(Alpine);
registerQuickCodexEntry(Alpine);

Alpine.start();

// story/index.blade.php's move buttons call this via a plain inline
// onclick (see scene-reorder.js's docblock for why it isn't an Alpine
// component), so it must stay reachable on window.
window.moveScene = moveScene;

// The Save event button in x-single-event-field calls this from an Alpine
// x-on:click, which reads globals rather than module imports.
window.saveQuickEvent = saveQuickEvent;
