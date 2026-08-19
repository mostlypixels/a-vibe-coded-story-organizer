/** Detect structures that lose information during editor serialization. */

const MERGEABLE_CELL_NODE_TYPES = ['tableCell', 'tableHeader'];

function walk(node, visit) {
    if (!node || typeof node !== 'object') return;

    visit(node);
    (node.content || []).forEach((child) => walk(child, visit));
}

/** @param {object} doc ProseMirror JSON from `editor.getJSON()`. */
export function hasMergedTableCell(doc) {
    let found = false;

    walk(doc, (node) => {
        if (found || !MERGEABLE_CELL_NODE_TYPES.includes(node.type)) return;

        const { colspan = 1, rowspan = 1 } = node.attrs || {};
        if (colspan > 1 || rowspan > 1) found = true;
    });

    return found;
}

/** @param {object} doc ProseMirror JSON from `editor.getJSON()`. */
export function hasResizedImage(doc) {
    let found = false;

    walk(doc, (node) => {
        if (found || node.type !== 'image') return;

        const { width, height } = node.attrs || {};
        if (width !== null && width !== undefined && width !== '') found = true;
        if (height !== null && height !== undefined && height !== '') found = true;
    });

    return found;
}

/** Get selectors from the live schema to prevent a separate list from drifting. */
function registeredSelectors(schema) {
    const selectors = [];

    const collectFrom = (typeMap) => {
        Object.values(typeMap).forEach((type) => {
            (type.spec.parseDOM || []).forEach((rule) => {
                if (rule.tag) selectors.push(rule.tag);
            });
        });
    };

    collectFrom(schema.nodes);
    collectFrom(schema.marks);

    return selectors;
}

function matchesAnySelector(element, selectors) {
    return selectors.some((selector) => {
        try {
            return element.matches(selector);
        } catch {
            // Report an unsupported selector as unmatched instead of stopping the save.
            return false;
        }
    });
}

/**
 * Check source before parsing because ProseMirror removes unknown wrapper tags.
 * Check only top-level elements because known nodes own their child markup.
 *
 * @param {string} source Raw HTML or Markdown source.
 * @param {import('@tiptap/core').Editor} editor
 */
export function hasUnmatchedHtmlWrapperTag(source, editor) {
    // A structural result is not reliable without a DOM.
    if (typeof window === 'undefined' || typeof window.DOMParser === 'undefined') {
        return false;
    }

    const selectors = registeredSelectors(editor.schema);
    const dom = new window.DOMParser().parseFromString(`<body>${source || ''}</body>`, 'text/html');

    return Array.from(dom.body.children).some((element) => !matchesAnySelector(element, selectors));
}

/**
 * @param {object} params
 * @param {import('@tiptap/core').Editor} params.editor
 * @param {string} params.source Raw HTML or Markdown source.
 * @returns {Array<'mergedTableCell'|'resizedImage'|'unmatchedHtmlWrapperTag'>}
 */
export function findFallbackWarnings({ editor, source }) {
    const doc = editor.getJSON();
    const warnings = [];

    if (hasMergedTableCell(doc)) warnings.push('mergedTableCell');
    if (hasResizedImage(doc)) warnings.push('resizedImage');
    if (hasUnmatchedHtmlWrapperTag(source, editor)) warnings.push('unmatchedHtmlWrapperTag');

    return warnings;
}
