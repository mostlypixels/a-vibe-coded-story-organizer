import { Editor, Extension } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { Placeholder } from '@tiptap/extensions';
import { Markdown } from '@tiptap/markdown';
import { Suggestion } from '@tiptap/suggestion';

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
 *     ul, ol, li, blockquote, code, pre, a, br, hr; http/https schemes). StarterKit v3
 *     bundles exactly those nodes/marks; headings are capped 1–4 and links restricted
 *     to http/https to keep the two lists in sync. The server-side HtmlSanitizer is
 *     the real gate — this is belt-and-braces.
 *   - `markdown`: the value is clean CommonMark (Scene contents). Serialized via the
 *     official @tiptap/markdown extension (getMarkdown / contentType: 'markdown'), with
 *     Underline and Strike disabled because neither round-trips to clean CommonMark.
 *     The server-side ValidMarkdown rule + Str::markdown() render stay the real gate.
 *
 * The slash (`/`) command menu and the toolbar produce the same commands, so neither
 * can introduce a node/mark outside the format's allowed set.
 */

/**
 * Command descriptors for the `/` slash menu. Each one reuses the exact StarterKit
 * command the toolbar already calls, so the slash menu adds no new node/mark surface.
 * `mdHide` items (Underline, Strike) are dropped in markdown mode — they don't
 * serialize to clean CommonMark.
 */
function buildSlashItems(format, onLink) {
    const at = (editor, range) => editor.chain().focus().deleteRange(range);

    const items = [
        { title: 'Text', keywords: ['paragraph', 'p', 'body'], run: ({ editor, range }) => at(editor, range).setParagraph().run() },
        { title: 'Heading 1', keywords: ['h1', 'title'], run: ({ editor, range }) => at(editor, range).toggleHeading({ level: 1 }).run() },
        { title: 'Heading 2', keywords: ['h2'], run: ({ editor, range }) => at(editor, range).toggleHeading({ level: 2 }).run() },
        { title: 'Heading 3', keywords: ['h3'], run: ({ editor, range }) => at(editor, range).toggleHeading({ level: 3 }).run() },
        { title: 'Heading 4', keywords: ['h4'], run: ({ editor, range }) => at(editor, range).toggleHeading({ level: 4 }).run() },
        { title: 'Bold', keywords: ['strong', 'b'], run: ({ editor, range }) => at(editor, range).toggleBold().run() },
        { title: 'Italic', keywords: ['emphasis', 'i'], run: ({ editor, range }) => at(editor, range).toggleItalic().run() },
        { title: 'Underline', keywords: ['u'], mdHide: true, run: ({ editor, range }) => at(editor, range).toggleUnderline().run() },
        { title: 'Strikethrough', keywords: ['strike', 's'], mdHide: true, run: ({ editor, range }) => at(editor, range).toggleStrike().run() },
        { title: 'Bulleted list', keywords: ['ul', 'bullet', 'unordered'], run: ({ editor, range }) => at(editor, range).toggleBulletList().run() },
        { title: 'Numbered list', keywords: ['ol', 'ordered', 'number'], run: ({ editor, range }) => at(editor, range).toggleOrderedList().run() },
        { title: 'Blockquote', keywords: ['quote', 'citation'], run: ({ editor, range }) => at(editor, range).toggleBlockquote().run() },
        { title: 'Inline code', keywords: ['code', 'mono'], run: ({ editor, range }) => at(editor, range).toggleCode().run() },
        { title: 'Code block', keywords: ['codeblock', 'pre', 'fenced'], run: ({ editor, range }) => at(editor, range).toggleCodeBlock().run() },
        // Link reuses the component's setLink() prompt so the http/https guard is shared.
        { title: 'Link', keywords: ['url', 'href', 'a'], run: ({ editor, range }) => { at(editor, range).run(); onLink(); } },
        { title: 'Horizontal rule', keywords: ['hr', 'divider', 'rule'], run: ({ editor, range }) => at(editor, range).setHorizontalRule().run() },
    ];

    return format === 'markdown' ? items.filter((item) => !item.mdHide) : items;
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
function slashExtension(format, onLink) {
    const menuItems = buildSlashItems(format, onLink);

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

                const extensions = [
                    StarterKit.configure({
                        heading: { levels: [1, 2, 3, 4] },
                        link: {
                            openOnClick: false,
                            autolink: true,
                            protocols: ['http', 'https'],
                            HTMLAttributes: { rel: null, target: null },
                        },
                        // Underline/Strike don't round-trip to clean CommonMark, so drop
                        // them from the markdown field (matched by toolbar + slash menu).
                        ...(isMarkdown ? { strike: false, underline: false } : {}),
                    }),
                    Placeholder.configure({
                        placeholder: config.placeholder || '',
                    }),
                    slashExtension(config.format || 'html', () => this.setLink()),
                ];

                if (isMarkdown) {
                    extensions.push(Markdown);
                }

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

            /** Whether a mark/node is active at the cursor (reactive via `tick`). */
            isOn(name, arg) {
                return this.tick >= 0 && !!editor && editor.isActive(name, arg);
            },
        };
    });
}
