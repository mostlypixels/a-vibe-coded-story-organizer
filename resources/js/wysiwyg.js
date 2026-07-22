import { Editor, Extension, Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { Placeholder } from '@tiptap/extensions';
import { Markdown } from '@tiptap/markdown';
import { Suggestion } from '@tiptap/suggestion';
import { Table, TableRow, TableHeader, TableCell } from '@tiptap/extension-table';
import Image from '@tiptap/extension-image';
import { TaskItem, TaskList } from '@tiptap/extension-list';
import { Underline } from '@tiptap/extension-underline';

/**
 * Table's stock renderHTML() always emits a `style="width: …"/"min-width: …"`
 * attribute on <table> plus a <colgroup>/<col> pair for column-width bookkeeping —
 * both purely presentational. This app never enables table column-resize (only
 * merge/split, an HTML-mode-only toolbar affordance — see buildExtensions() below),
 * and RichTextFields deliberately allows no presentational attributes anywhere
 * (style/class) on any tag. Rather than widen the server-side allow-list to accept
 * `style`/`colgroup`/`col` for a feature this app doesn't offer, this override drops
 * them from the *serialized* output, keeping only what merge/split actually needs to
 * round-trip: `colspan`/`rowspan` on <td>/<th> (untouched — TableCell/TableHeader's
 * own renderHTML, not overridden here). This only changes getHTML()/save output — the
 * interactive editor still shows Table's own column-width node view (TableView) while
 * editing, since that's a separate DOM path (addNodeView) from renderHTML/toDOM.
 */
const PlainTable = Table.extend({
    renderHTML({ HTMLAttributes }) {
        return ['table', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes), ['tbody', 0]];
    },
});

/**
 * Underline has no clean CommonMark equivalent, so `@tiptap/extension-underline`'s
 * stock renderMarkdown() invents its own `++text++` dialect — a syntax nothing else
 * in this app's Markdown grammar recognizes. Rather than adopt that bespoke syntax,
 * this app makes `<u>text</u>` (raw inline HTML) the one sanctioned HTML-passthrough
 * exception in an otherwise fully-tokenized Markdown field (expand-tip-tap task 05 /
 * spec.md's "Underline" decision) — do not generalize this pattern to any other mark
 * without a fresh decision. Reading `<u>` needs no override here: the mark's inherited
 * `parseHTML()` (`{ tag: 'u' }`, unmodified) already fires whenever @tiptap/markdown's
 * parser hits raw inline HTML, via CommonMark's own raw-HTML passthrough — the same
 * mechanism that already renders `> [!TYPE]` callouts as plain blockquotes today.
 * `markdownTokenizer: null` disables the stock `++...++` tokenizer entirely, so `++`
 * is never a second, undecided-upon way to spell underline in this app's Markdown.
 */
const MarkdownUnderline = Underline.extend({
    markdownTokenizer: null,
    renderMarkdown(node, helpers) {
        return `<u>${helpers.renderChildren(node)}</u>`;
    },
});

/**
 * The five GitHub-flavoured alert/callout types (`> [!NOTE]` etc.), already used in
 * this repo's own documentation/*.md (per CLAUDE.md). Lower-case is the canonical
 * attribute value; the Markdown marker is spelled upper-case (`[!NOTE]`).
 */
const CALLOUT_TYPES = ['note', 'tip', 'important', 'warning', 'caution'];

/**
 * Matches a blockquote's first line when it is exactly a callout marker — the marker
 * alone on its line (trailing spaces allowed), nothing else. `[!NOTE] text` on the
 * same line is deliberately NOT a callout (matching GitHub), so it falls through to
 * the plain Blockquote handler. Capture group 1 is the upper-case type.
 */
const CALLOUT_MARKER = /^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\][ \t]*(?:\n|$)/;

/**
 * Custom node for GitHub's `> [!TYPE]` alert/callout convention. It is a *sibling* of
 * StarterKit's Blockquote — NOT a modification of it — so a plain blockquote with no
 * `[!TYPE]` marker is entirely unaffected (parses/renders exactly as before).
 *
 * Markdown side (`markdownTokenName: 'blockquote'`): this node's parseMarkdown is tried
 * on every blockquote token before Blockquote's own handler (higher `priority`), and
 * returns null for any blockquote whose first line is not exactly `[!TYPE]` — so only
 * real callouts become Callout nodes; everything else falls through to Blockquote.
 * renderMarkdown re-emits `> [!TYPE]` + `> `-prefixed content, byte-for-byte matching
 * the input convention. That exactness is a correctness requirement, not cosmetics:
 * it is what lets a plain-CommonMark reader (Scene::renderedContents, EPUB export, the
 * share page — none of which know about callouts) keep degrading a callout gracefully
 * into an ordinary blockquote, exactly as it does today with zero code changes.
 *
 * HTML side (the 8 RichTextFields fields): presentational only — a `data-callout-type`
 * attribute on the existing <blockquote> element (allow-listed by task 01, no new tag),
 * styled into a coloured box by resources/css/app.css. The parseHTML rule carries an
 * explicit priority so an attributed <blockquote data-callout-type> resolves to this
 * node rather than the plain Blockquote (whose rule matches any <blockquote>).
 */
const Callout = Node.create({
    name: 'callout',

    // Above Blockquote's default (100) so, when parsing Markdown, this node's
    // parseMarkdown handler is registered — and therefore tried — before Blockquote's
    // for the shared `blockquote` token type.
    priority: 200,

    group: 'block',
    content: 'block+',
    defining: true,

    addAttributes() {
        return {
            calloutType: {
                default: 'note',
                parseHTML: (element) => {
                    const type = (element.getAttribute('data-callout-type') || '').toLowerCase();

                    return CALLOUT_TYPES.includes(type) ? type : 'note';
                },
                renderHTML: (attributes) => ({ 'data-callout-type': attributes.calloutType }),
            },
        };
    },

    parseHTML() {
        // Attribute selector + explicit priority so this wins over Blockquote's plain
        // `blockquote` rule (default priority 50) for an attributed blockquote, while a
        // bare <blockquote> never matches here and stays an ordinary Blockquote.
        return [{ tag: 'blockquote[data-callout-type]', priority: 60 }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['blockquote', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes), 0];
    },

    addCommands() {
        const normalize = (attributes) => ({
            calloutType: CALLOUT_TYPES.includes(attributes?.type) ? attributes.type : 'note',
        });

        return {
            // Wrap the current block(s) in a callout of the given `{ type }` (default note).
            setCallout:
                (attributes = {}) =>
                ({ commands }) =>
                    commands.wrapIn(this.name, normalize(attributes)),
            // Change the type of the callout the cursor is in without re-wrapping.
            updateCalloutType:
                (attributes = {}) =>
                ({ commands }) =>
                    commands.updateAttributes(this.name, normalize(attributes)),
            // Lift the current block(s) back out of the callout (plain paragraph/blockquote).
            unsetCallout:
                () =>
                ({ commands }) =>
                    commands.lift(this.name),
        };
    },

    /**
     * `markdownTokenName: 'blockquote'` registers this node's parseMarkdown against the
     * `blockquote` token marked emits, so it can intercept callout-shaped blockquotes.
     */
    markdownTokenName: 'blockquote',

    parseMarkdown(token, helpers) {
        const blockTokens = token.tokens || [];
        const firstBlock = blockTokens[0];

        // Only a blockquote whose first block is a paragraph starting with a lone
        // `[!TYPE]` marker line is a callout; anything else → null → Blockquote handles it.
        if (!firstBlock || firstBlock.type !== 'paragraph') {
            return null;
        }

        const match = (firstBlock.text || '').match(CALLOUT_MARKER);

        if (!match) {
            return null;
        }

        const calloutType = match[1].toLowerCase();
        const remainder = (firstBlock.text || '').slice(match[0].length);

        // Rebuild the child token list: drop the marker line, keep any content that
        // shared the first paragraph (lazy/soft-break continuation), then the rest.
        const restTokens = blockTokens.slice(1).filter((child) => child.type !== 'space');
        const childTokens = remainder.trim() !== ''
            ? [{ type: 'paragraph', text: remainder, tokens: helpers.tokenizeInline(remainder) }, ...restTokens]
            : restTokens;

        const content = helpers.parseBlockChildren(childTokens);

        return helpers.createNode(
            'callout',
            { calloutType },
            // content is `block+`: guarantee at least one block even for an empty callout.
            content.length > 0 ? content : [{ type: 'paragraph' }],
        );
    },

    renderMarkdown(node, helpers) {
        const calloutType = (node.attrs?.calloutType || 'note').toUpperCase();
        const prefix = '>';
        const markerLine = `${prefix} [!${calloutType}]`;

        const body = (node.content || [])
            .map((child, index) => {
                const rendered = helpers.renderChild?.(child, index) ?? helpers.renderChildren([child]);

                return rendered
                    .split('\n')
                    .map((line) => (line.trim() === '' ? prefix : `${prefix} ${line}`))
                    .join('\n');
            })
            .join(`\n${prefix}\n`);

        return body ? `${markerLine}\n${body}` : markerLine;
    },
});

/**
 * The single, library-agnostic integration point for the WYSIWYG editor (Tiptap).
 * Everything else in the app talks to the editor only through the `x-wysiwyg` Blade
 * component and this Alpine component, so swapping libraries never touches a view.
 *
 * Progressive enhancement: the Blade component renders a real <textarea> holding the
 * value. This component mounts the editor over it, hydrates from the textarea, and
 * syncs edits back into the textarea (on every change and again on submit) so the
 * ordinary, no-JS form submit carries the value and old() repopulates on failure.
 *
 * Two field formats share this one component:
 *   - `html` (default): the value is sanitized HTML. Output MUST stay within the
 *     task-01 allow-list in App\Support\RichTextFields (p, h1–h4, strong, em, u, s,
 *     ul, ol, li, blockquote, code, pre, a, br, hr, plus table/img/task-list markup;
 *     http/https schemes). StarterKit v3 bundles the base nodes/marks; headings are
 *     capped 1–4 and links restricted to http/https to keep the two lists in sync.
 *     The server-side HtmlSanitizer is the real gate — this is belt-and-braces.
 *   - `markdown`: the value is clean CommonMark (Scene contents). Serialized via the
 *     official @tiptap/markdown extension (getMarkdown / contentType: 'markdown').
 *     Strikethrough is standard GFM (`~~text~~`), no custom handler needed. Underline
 *     has no CommonMark equivalent, so it round-trips via `<u>text</u>` raw-HTML
 *     passthrough — the one sanctioned HTML exception in this field (see
 *     MarkdownUnderline above). The server-side ValidMarkdown rule + Str::markdown()
 *     render stay the real gate.
 *   - Table, Image, and TaskItem/TaskList (expand-tip-tap task 03) apply unconditionally
 *     to both formats — all three ship real parseMarkdown/renderMarkdown handlers
 *     (@tiptap/extension-table, @tiptap/extension-image, @tiptap/extension-list), so
 *     no hand-written serializer is needed. Image resize and table merge/split
 *     (expand-tip-tap task 04) are HTML-mode-only: both are lossless there but lossy in
 *     Markdown, so `Image.configure({ resize: … })` and the merge/split toolbar entries
 *     only turn on when `! isMarkdown`.
 *
 * The slash (`/`) command menu and the toolbar produce the same commands, so neither
 * can introduce a node/mark outside the format's allowed set.
 */

/**
 * Command descriptors for the `/` slash menu. Each one reuses the exact StarterKit
 * command the toolbar already calls, so the slash menu adds no new node/mark surface.
 * Underline and Strikethrough round-trip in both formats (expand-tip-tap task 05), so
 * neither carries an `mdHide` flag any more.
 */
/**
 * Exported (in addition to `buildExtensions`) so the vitest suite can assert on the
 * per-format item list directly — e.g. confirming no merge/split-cell entry exists in
 * either format's slash menu (ui.md: merging is a post-insertion table operation, not
 * something a slash command inserts fresh) — without re-deriving it from a live editor.
 */
export function buildSlashItems(format, onLink, onImage) {
    const at = (editor, range) => editor.chain().focus().deleteRange(range);

    const items = [
        { title: 'Text', keywords: ['paragraph', 'p', 'body'], run: ({ editor, range }) => at(editor, range).setParagraph().run() },
        { title: 'Heading 1', keywords: ['h1', 'title'], run: ({ editor, range }) => at(editor, range).toggleHeading({ level: 1 }).run() },
        { title: 'Heading 2', keywords: ['h2'], run: ({ editor, range }) => at(editor, range).toggleHeading({ level: 2 }).run() },
        { title: 'Heading 3', keywords: ['h3'], run: ({ editor, range }) => at(editor, range).toggleHeading({ level: 3 }).run() },
        { title: 'Heading 4', keywords: ['h4'], run: ({ editor, range }) => at(editor, range).toggleHeading({ level: 4 }).run() },
        { title: 'Bold', keywords: ['strong', 'b'], run: ({ editor, range }) => at(editor, range).toggleBold().run() },
        { title: 'Italic', keywords: ['emphasis', 'i'], run: ({ editor, range }) => at(editor, range).toggleItalic().run() },
        { title: 'Underline', keywords: ['u'], run: ({ editor, range }) => at(editor, range).toggleUnderline().run() },
        { title: 'Strikethrough', keywords: ['strike', 's'], run: ({ editor, range }) => at(editor, range).toggleStrike().run() },
        { title: 'Bulleted list', keywords: ['ul', 'bullet', 'unordered'], run: ({ editor, range }) => at(editor, range).toggleBulletList().run() },
        { title: 'Numbered list', keywords: ['ol', 'ordered', 'number'], run: ({ editor, range }) => at(editor, range).toggleOrderedList().run() },
        { title: 'Blockquote', keywords: ['quote', 'citation'], run: ({ editor, range }) => at(editor, range).toggleBlockquote().run() },
        { title: 'Inline code', keywords: ['code', 'mono'], run: ({ editor, range }) => at(editor, range).toggleCode().run() },
        { title: 'Code block', keywords: ['codeblock', 'pre', 'fenced'], run: ({ editor, range }) => at(editor, range).toggleCodeBlock().run() },
        // Link reuses the component's setLink() prompt so the http/https guard is shared.
        { title: 'Link', keywords: ['url', 'href', 'a'], run: ({ editor, range }) => { at(editor, range).run(); onLink(); } },
        { title: 'Horizontal rule', keywords: ['hr', 'divider', 'rule'], run: ({ editor, range }) => at(editor, range).setHorizontalRule().run() },
        // Table/Image/Task list apply unconditionally to both formats (expand-tip-tap
        // task 03) — no mdHide here. Resize and merge/split are HTML-mode-only, but
        // that's a toolbar-only concern (see wysiwyg.blade.php): merging is a
        // post-insertion operation on an existing table, not something a slash command
        // inserts fresh, so there is no merge/split slash entry in either format.
        { title: 'Table', keywords: ['table', 'grid'], run: ({ editor, range }) => at(editor, range).insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run() },
        { title: 'Image', keywords: ['image', 'img', 'picture'], run: ({ editor, range }) => { at(editor, range).run(); onImage(); } },
        { title: 'Task list', keywords: ['todo', 'checklist', 'checkbox'], run: ({ editor, range }) => at(editor, range).toggleTaskList().run() },
        // Callout (`> [!TYPE]`) applies to both formats (task 06) — not format-gated.
        // Inserts a `note` callout; the type is cycled afterwards from the toolbar.
        { title: 'Callout', keywords: ['note', 'tip', 'warning', 'alert', 'callout'], run: ({ editor, range }) => at(editor, range).setCallout({ type: 'note' }).run() },
    ];

    // No item is format-gated any more: every command here round-trips in both
    // formats (Underline/Strike as of expand-tip-tap task 05). Table/Image/Task
    // list already didn't need a gate (task 03) — only merge/split (an existing
    // table's post-insertion operation, not something a slash command inserts
    // fresh) has no slash entry in either format.
    return items;
}

/**
 * The `/` slash-menu popup renderer. Uses the suggestion plugin's managed positioning
 * (props.mount → floating-ui, already bundled by @tiptap/suggestion) so there is no
 * new positioning dependency and no manual caret-coordinate math.
 */
function slashRenderer() {
    let el = null;
    let unmount = null;
    let items = [];
    let selected = 0;
    let command = null;
    let closed = false;

    const paint = () => {
        if (!el) return;
        el.innerHTML = '';

        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'wysiwyg-slash__empty';
            empty.textContent = 'No matches';
            el.appendChild(empty);
            return;
        }

        items.forEach((item, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'wysiwyg-slash__item' + (index === selected ? ' is-selected' : '');
            button.textContent = item.title;
            // mousedown (not click) so the editor keeps its selection/range.
            button.addEventListener('mousedown', (event) => {
                event.preventDefault();
                command(item);
            });
            button.addEventListener('mouseenter', () => {
                selected = index;
                paint();
            });
            el.appendChild(button);
        });
    };

    return {
        onStart: (props) => {
            closed = false;
            items = props.items;
            selected = 0;
            command = props.command;
            el = document.createElement('div');
            el.className = 'wysiwyg-slash';
            paint();
            unmount = props.mount(el);
        },
        onUpdate: (props) => {
            if (closed) return;
            items = props.items;
            command = props.command;
            if (selected >= items.length) selected = 0;
            paint();
        },
        onKeyDown: (props) => {
            const { event } = props;

            if (event.key === 'Escape') {
                closed = true;
                unmount?.();
                unmount = null;
                el = null;
                return true;
            }
            if (!items.length) return false;

            if (event.key === 'ArrowDown') {
                selected = (selected + 1) % items.length;
                paint();
                return true;
            }
            if (event.key === 'ArrowUp') {
                selected = (selected - 1 + items.length) % items.length;
                paint();
                return true;
            }
            if (event.key === 'Enter') {
                command(items[selected]);
                return true;
            }

            return false;
        },
        onExit: () => {
            unmount?.();
            unmount = null;
            el = null;
            closed = false;
        },
    };
}

/** Build the slash-command extension for a given field format. */
function slashExtension(format, onLink, onImage) {
    const menuItems = buildSlashItems(format, onLink, onImage);

    return Extension.create({
        name: 'slashCommands',
        addProseMirrorPlugins() {
            return [
                Suggestion({
                    // `this` is the Tiptap extension instance here, not the Alpine
                    // component — so this is the extension's editor, not the closure var.
                    editor: this.editor,
                    char: '/',
                    command: ({ editor, range, props }) => props.run({ editor, range }),
                    items: ({ query }) => {
                        const q = query.toLowerCase();
                        if (!q) return menuItems;

                        return menuItems.filter(
                            (item) =>
                                item.title.toLowerCase().includes(q) ||
                                (item.keywords || []).some((keyword) => keyword.includes(q))
                        );
                    },
                    render: slashRenderer,
                }),
            ];
        },
    });
}

/**
 * Build the shared `extensions` array for a given field format. Exported (in
 * addition to being used by `registerWysiwyg`'s `init()`) so the vitest round-trip
 * suite (`wysiwyg.test.js`) exercises the exact same extension configuration the
 * live editor uses, rather than a hand-maintained copy that could drift.
 */
export function buildExtensions(format, { placeholder = '', onLink = () => {}, onImage = () => {} } = {}) {
    const isMarkdown = format === 'markdown';

    const extensions = [
        StarterKit.configure({
            heading: { levels: [1, 2, 3, 4] },
            link: {
                openOnClick: false,
                autolink: true,
                protocols: ['http', 'https'],
                HTMLAttributes: { rel: null, target: null },
            },
            // StarterKit's stock Underline is replaced by MarkdownUnderline below
            // (same mark, `<u>` passthrough on the Markdown side) in both formats,
            // so the two extensions never both register the 'underline' mark name.
            // Strike needs no such swap: `~~text~~` is standard GFM and already
            // round-trips via StarterKit's stock Strike, unconditionally.
            underline: false,
        }),
        Placeholder.configure({ placeholder }),
        MarkdownUnderline,
        // Table/Image/TaskItem/TaskList apply unconditionally to both formats —
        // round-trip support is symmetric (task 03 of expand-tip-tap). Resize
        // (image) and merge/split (table) are HTML-mode-only (task 04): both are
        // lossy for Markdown-mode fields, so they stay off there.
        PlainTable,
        TableRow,
        TableHeader,
        TableCell,
        Image.configure({ inline: false, resize: isMarkdown ? false : { enabled: true } }),
        TaskItem,
        TaskList,
        // Callout (`> [!TYPE]`) applies to both formats (expand-tip-tap task 06): in
        // Markdown it serializes back to the exact `> [!TYPE]` convention; in HTML it
        // presents over <blockquote> via the data-callout-type attribute (task 01).
        Callout,
        slashExtension(format, onLink, onImage),
    ];

    if (isMarkdown) {
        extensions.push(Markdown);
    }

    return extensions;
}

export function registerWysiwyg(Alpine) {
    Alpine.data('wysiwyg', (config = {}) => {
        // The Tiptap Editor is kept in a plain closure variable, NOT on the reactive
        // `this`: Alpine wraps reactive properties in a Proxy, and ProseMirror's
        // view/state do not survive being proxied — toolbar commands would silently
        // no-op (the slash menu worked only because it uses the raw editor the
        // suggestion plugin hands it). Only `ready`/`tick` need reactivity.
        let editor = null;

        return {
            ready: false,
            // A monotonic counter bumped on every editor transaction. Toolbar bindings
            // read it (via isOn) so Alpine recomputes active states as the selection moves.
            tick: 0,

            init() {
                const textarea = this.$refs.textarea;
                const mount = this.$refs.editor;
                const isMarkdown = config.format === 'markdown';

                const syncTextarea = (instance) => {
                    // Keep "empty" empty: getHTML() returns "<p></p>" for a blank doc,
                    // which would defeat the nullable/empty handling on the server.
                    if (instance.isEmpty) {
                        textarea.value = '';
                        return;
                    }
                    textarea.value = isMarkdown ? instance.getMarkdown() : instance.getHTML();
                };

                const extensions = buildExtensions(config.format || 'html', {
                    placeholder: config.placeholder || '',
                    onLink: () => this.setLink(),
                    onImage: () => this.setImage(),
                });

                editor = new Editor({
                    element: mount,
                    editable: !config.disabled,
                    content: textarea.value || '',
                    ...(isMarkdown ? { contentType: 'markdown' } : {}),
                    extensions,
                    editorProps: {
                        attributes: {
                            class: 'prose prose-sm max-w-none focus:outline-none px-3 py-2',
                            style: config.minHeight ? `min-height: ${config.minHeight}` : '',
                        },
                    },
                    onUpdate: ({ editor: instance }) => syncTextarea(instance),
                    onTransaction: () => {
                        this.tick++;
                    },
                });

                // Belt-and-braces: sync once more on submit in case a change is mid-flight.
                const form = this.$el.closest('form');
                if (form) {
                    form.addEventListener('submit', () => syncTextarea(editor));
                }

                this.ready = true;
            },

            destroy() {
                editor?.destroy();
            },

            /** Run a Tiptap chain command (e.g. 'toggleBold', 'toggleHeading') focused. */
            cmd(name, arg) {
                if (!editor) return;
                editor.chain().focus()[name](arg).run();
            },

            /** Toggle a link, prompting for an http/https URL (allow-list schemes only). */
            setLink() {
                if (!editor) return;

                if (editor.isActive('link')) {
                    editor.chain().focus().unsetLink().run();
                    return;
                }

                const previous = editor.getAttributes('link').href || '';
                const url = window.prompt(config.linkPrompt || 'Enter a URL (http:// or https://)', previous);

                if (url === null) return; // cancelled
                if (url === '') {
                    editor.chain().focus().unsetLink().run();
                    return;
                }
                if (!/^https?:\/\//i.test(url)) return; // keep output within the allow-list

                editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
            },

            /**
             * Insert an image, prompting for an http/https URL (allow-list schemes
             * only, mirroring setLink()'s guard) and optional alt text.
             */
            setImage() {
                if (!editor) return;

                const url = window.prompt(config.imagePrompt || 'Enter an image URL (http:// or https://)');
                if (url === null || url === '') return; // cancelled or empty

                if (!/^https?:\/\//i.test(url)) return; // keep output within the allow-list

                const alt = window.prompt(config.imageAltPrompt || 'Alt text (optional, for accessibility)') || '';

                editor.chain().focus().setImage({ src: url, alt }).run();
            },

            /**
             * Callout button: insert a `[!NOTE]` callout when the cursor is not already
             * in one, otherwise cycle the existing callout to the next of the five types
             * (note → tip → important → warning → caution → note). Cycling in place is
             * the simplest type-picker that fits the toolbar's glyph-button language — no
             * dropdown or prompt — and every type stays reachable with repeated clicks.
             */
            toggleCallout() {
                if (!editor) return;

                if (editor.isActive('callout')) {
                    const current = editor.getAttributes('callout').calloutType || 'note';
                    const next = CALLOUT_TYPES[(CALLOUT_TYPES.indexOf(current) + 1) % CALLOUT_TYPES.length];
                    editor.chain().focus().updateCalloutType({ type: next }).run();
                    return;
                }

                editor.chain().focus().setCallout({ type: 'note' }).run();
            },

            /** Whether a mark/node is active at the cursor (reactive via `tick`). */
            isOn(name, arg) {
                return this.tick >= 0 && !!editor && editor.isActive(name, arg);
            },
        };
    });
}
