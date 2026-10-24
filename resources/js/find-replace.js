import { translate } from './translate';

/**
 * The match selection of the find and replace preview.
 *
 * The state drives the check boxes, so "Select all" and "Select none" also reach folded
 * scenes. A submit writes the selection as one JSON field (`max_input_vars`).
 */

/** Above this many matches, the author confirms in an inline panel before the post. */
export const CONFIRM_ABOVE = 50;

/** Translation keys. `App\Support\ScriptTranslations::findReplace()` supplies the text. */
export const FIND_REPLACE_COPY = {
    counterOne: ':selected of :total match selected in :scenes',
    counterMany: ':selected of :total matches selected in :scenes',
    scenesOne: '1 scene',
    scenesMany: ':count scenes',
    replaceOne: 'Replace 1 match',
    replaceMany: 'Replace :count matches',
    confirm: 'Replace :count matches in :scenes?',
};

export function fill(template, values) {
    return template.replace(/:(\w+)/g, (token, name) => (name in values ? String(values[name]) : token));
}

/**
 * @param {Record<string, {hash: string, indexes: number[]}>} scenes every match of the preview, keyed by scene ID
 * @param {Record<string, string>} strings
 */
export function findReplace(scenes, strings = {}) {
    const text = (key, values) => fill(translate(strings, FIND_REPLACE_COPY[key]), values);
    const scenesText = (count) => text(count === 1 ? 'scenesOne' : 'scenesMany', { count });

    return {
        scenes,
        selection: Object.fromEntries(Object.entries(scenes).map(([id, scene]) => [id, new Set(scene.indexes)])),
        // The scene ID of a submit that waits for confirmation, or 'all'. Null when none waits.
        pending: null,

        isSelected(sceneId, index) {
            return this.selection[sceneId].has(index);
        },

        toggle(sceneId, index) {
            const chosen = this.selection[sceneId];

            if (chosen.has(index)) {
                chosen.delete(index);
            } else {
                chosen.add(index);
            }
        },

        /** @returns {'all'|'some'|'none'} */
        sceneState(sceneId) {
            const size = this.selection[sceneId].size;

            if (size === 0) {
                return 'none';
            }

            return size === this.scenes[sceneId].indexes.length ? 'all' : 'some';
        },

        toggleScene(sceneId) {
            const indexes = this.sceneState(sceneId) === 'all' ? [] : this.scenes[sceneId].indexes;
            this.selection[sceneId] = new Set(indexes);
        },

        selectAll() {
            for (const id of Object.keys(this.scenes)) {
                this.selection[id] = new Set(this.scenes[id].indexes);
            }
        },

        selectNone() {
            for (const id of Object.keys(this.scenes)) {
                this.selection[id] = new Set();
            }
        },

        /** The scene IDs a submit sends: one scene, or every scene with a selected match. */
        sceneIds(only = null) {
            const ids = only === null ? Object.keys(this.scenes) : [String(only)];

            return ids.filter((id) => this.selection[id]?.size > 0);
        },

        selectedCount(only = null) {
            return this.sceneIds(only).reduce((sum, id) => sum + this.selection[id].size, 0);
        },

        get totalCount() {
            return Object.values(this.scenes).reduce((sum, scene) => sum + scene.indexes.length, 0);
        },

        get counterText() {
            const total = this.totalCount;

            return text(total === 1 ? 'counterOne' : 'counterMany', {
                selected: this.selectedCount(),
                total,
                scenes: scenesText(this.sceneIds().length),
            });
        },

        replaceLabel(only = null) {
            const count = this.selectedCount(only);

            return count === 1 ? text('replaceOne', {}) : text('replaceMany', { count });
        },

        get confirmText() {
            const only = this.pending === 'all' ? null : this.pending;

            return text('confirm', {
                count: this.selectedCount(only),
                scenes: scenesText(this.sceneIds(only).length),
            });
        },

        /** @returns {Record<string, {hash: string, indexes: number[]}>} the shape FindReplaceApplyRequest reads */
        payload(only = null) {
            return Object.fromEntries(
                this.sceneIds(only).map((id) => [
                    id,
                    { hash: this.scenes[id].hash, indexes: [...this.selection[id]].sort((a, b) => a - b) },
                ]),
            );
        },

        request(only = null) {
            const count = this.selectedCount(only);

            if (count === 0) {
                return;
            }

            if (count > CONFIRM_ABOVE) {
                this.pending = only === null ? 'all' : String(only);

                return;
            }

            this.send(only);
        },

        accept() {
            if (this.pending !== null) {
                this.send(this.pending === 'all' ? null : this.pending);
            }
        },

        cancel() {
            this.pending = null;
        },

        send(only) {
            this.$refs.selection.value = JSON.stringify(this.payload(only));
            // submit() skips the submit event, so the form handler does not run again.
            this.$refs.form.submit();
        },

        /** Fold and unfold do not change the selection. */
        setAllOpen(open) {
            this.$root.querySelectorAll('details[data-scene-id]').forEach((details) => {
                details.open = open;
            });
        },
    };
}

export function registerFindReplace(Alpine) {
    Alpine.data('findReplace', findReplace);
}
