/**
 * Fallback-warning structural checks — the deliverable
 * `.specs/planned/2026-07/autosave-with-revisions` §11.5.2 depends on (see
 * expand-tip-tap's `spec.md`, "Fallback policy": prevent where cheap, warn from
 * an explicit list for the rest).
 *
 * Three residual, attribute/structure-level losses remain even with tables,
 * images, task lists, underline, strikethrough, and callouts all supported
 * (expand-tip-tap tasks 01-06): a merged table cell, a resized image, and an
 * HTML wrapper tag the schema doesn't claim. Each check below is STRUCTURAL —
 * it inspects the parsed document (or, for the third check, the raw source
 * string) directly — never a text diff. That is what makes the "no false
 * positives" guarantee possible: TipTap's own cosmetic Markdown
 * re-serialisation (`_em_` → `*em*`, reference-link → inline, bullet-marker
 * changes) never touches a node's attrs and never introduces an unrecognized
 * tag, so none of these three checks can ever misfire on it. Underline and
 * callouts need no check at all — both are designed to round-trip losslessly
 * (spec.md) — and footnotes are entirely out of this list (`footnote-plugin`
 * owns that decision).
 *
 * Deliberately standalone: this file imports nothing from `wysiwyg.js` (no
 * toolbar/slash-menu/Alpine glue), so a consumer — `autosave-with-revisions` —
 * can depend on just this one file. It only needs a live Tiptap `Editor`
 * instance (already built via `buildExtensions()` from `wysiwyg.js`, or any
 * compatible schema) and, for the third check, the raw source string that was
 * loaded into it.
 */

/** The two node types this app's schema can carry a merged cell on. */
const MERGEABLE_CELL_NODE_TYPES = ['tableCell', 'tableHeader'];

/**
 * Recursively visit every node in a ProseMirror JSON document
 * (`editor.getJSON()`), depth-first, including the root.
 */
function walk(node, visit) {
    if (!node || typeof node !== 'object') return;

    visit(node);
    (node.content || []).forEach((child) => walk(child, visit));
}

/**
 * Check 1 — a table containing a merged cell: a `tableCell`/`tableHeader`
 * node whose `colspan`/`rowspan` attribute is greater than 1 (the default,
 * un-merged value). A plain table's cells all default to colspan/rowspan 1
 * and never trip this, however many rows/columns it has.
 *
 * @param {object} doc Parsed ProseMirror document (`editor.getJSON()`).
 */
export function hasMergedTableCell(doc) {
    let found = false;

    walk(doc, (node) => {
        if (found || !MERGEABLE_CELL_NODE_TYPES.includes(node.type)) return;

        const { colspan = 1, rowspan = 1 } = node.attrs || {};
        if (colspan > 1 || rowspan > 1) found = true;
    });

    return found;
}

/**
 * Check 2 — an image with `width`/`height` attributes set. Only reachable via
 * paste/import for Markdown-mode fields today — task 04 ships resize as an
 * HTML-mode-only toolbar affordance, so an HTML-mode field's own UI never
 * produces this, but the check itself stays format-agnostic (per the task
 * file: "the caller, not this module, decides when to invoke it").
 *
 * @param {object} doc Parsed ProseMirror document (`editor.getJSON()`).
 */
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

/**
 * Collect the raw CSS selectors this schema's registered nodes/marks actually
 * claim via their `parseHTML()` rules — e.g. `blockquote[data-callout-type]`,
 * `img[src]:not([src^="data:"])`, `li[data-type="taskItem"]`, plain `table`.
 * Derived from the live schema rather than a hand-maintained copy, so it can
 * never drift from what `buildExtensions()` registers (matches this feature's
 * own precedent of reading installed source over assuming it — see
 * `../../.specs/planned/2026-07/expand-tip-tap/resolution-log.md`). Style-based
 * rules (`tag: null`, matched via inline `style=` instead of a tag) contribute
 * nothing here.
 */
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

/** Whether `element` matches at least one of the given CSS selectors. */
function matchesAnySelector(element, selectors) {
    return selectors.some((selector) => {
        try {
            return element.matches(selector);
        } catch {
            // A selector this DOM implementation can't evaluate is treated as
            // "doesn't match" rather than thrown — conservative, but this
            // should not happen in practice: every selector here came from a
            // real `parseHTML()` rule, which the same DOM already had to
            // support to hydrate the editor in the first place.
            return false;
        }
    });
}

/**
 * Check 3 — an HTML block whose outer tag matches no registered node/mark's
 * `parseHTML` rule (spec.md's `<div class="letter">…</div>` example).
 *
 * Checked against the RAW `source` string, not the parsed document: by the
 * time an unmatched wrapper tag has been parsed into a ProseMirror doc, the
 * wrapper is already gone — `@tiptap/markdown` (and ProseMirror's own DOM
 * parser for HTML-mode fields) transparently unwraps any tag no rule claims
 * and keeps only its content, so there is nothing left in the resulting doc
 * to detect (confirmed by reading `@tiptap/markdown`'s own
 * `parseHTMLToken`/`generateJSON` path — see spec.md's "Raw HTML blocks" note).
 * This is why this check takes `source` + `editor` (for its schema), unlike
 * the other two checks, which only need `editor.getJSON()`.
 *
 * Only the OUTERMOST elements of the source are examined: once an element
 * matches a registered rule, everything inside it is that node's own
 * business, not a wrapper a writer/paste introduced — a matched `<table>` is
 * never re-examined for its `<tbody>`/`<tr>`/`<td>` children, and a matched
 * `<ul data-type="taskList">` is never re-examined for the
 * `<label>`/`<input>`/`<span>`/`<div>` its own `TaskItem` rendering emits —
 * none of those inner tags have a `parseHTML` rule of their own; they are
 * simply never examined, because the ancestor that contains them already
 * matched. This is the mirror image of the "unwrap and keep the content"
 * mechanism described above, walked top-down instead of bottom-up.
 *
 * Only meaningful for source arriving from outside this editor's own
 * round-trip — paste, import, or a pre-existing scene. This app's own
 * `getHTML()`/`getMarkdown()` output never contains an unmatched wrapper tag
 * in the first place (the allow-list invariant in the plan's `00-overview.md`).
 *
 * @param {string} source Raw HTML or Markdown source that was (or will be)
 *   loaded into `editor`. Plain Markdown text with no literal HTML in it
 *   parses to zero DOM elements, so it can never trip this check.
 * @param {import('@tiptap/core').Editor} editor Any editor built from this
 *   app's schema (e.g. via `buildExtensions()` in `wysiwyg.js`) — used only to
 *   read its schema, never mutated.
 */
export function hasUnmatchedHtmlWrapperTag(source, editor) {
    // Mirrors @tiptap/markdown's own guard for the same constraint
    // (parseHTMLToken falls back to literal text outside a DOM environment) —
    // this check needs a real DOM to inspect structure, so outside one it
    // conservatively reports "no warning" rather than guessing.
    if (typeof window === 'undefined' || typeof window.DOMParser === 'undefined') {
        return false;
    }

    const selectors = registeredSelectors(editor.schema);
    const dom = new window.DOMParser().parseFromString(`<body>${source || ''}</body>`, 'text/html');

    return Array.from(dom.body.children).some((element) => !matchesAnySelector(element, selectors));
}

/**
 * The combined aggregate `autosave-with-revisions` §11.5.2 depends on:
 * which (if any) of the three structural cases apply to a given document.
 * Returns an array of warning keys (empty when none apply) — a document
 * tripping more than one check at once reports all of them, not just the
 * first found, since `autosave-with-revisions` will likely want both the
 * aggregate and the detail for its copy/UI.
 *
 * @param {object} params
 * @param {import('@tiptap/core').Editor} params.editor A hydrated editor
 *   instance (`editor.getJSON()` is used for checks 1 and 2).
 * @param {string} params.source The raw HTML/Markdown source that was loaded
 *   into `editor` (used for check 3 only).
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
