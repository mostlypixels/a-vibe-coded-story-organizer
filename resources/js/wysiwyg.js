import { Editor, Extension, Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { Placeholder } from '@tiptap/extensions';
import { Markdown } from '@tiptap/markdown';
import { Suggestion } from '@tiptap/suggestion';
import { Table, TableRow, TableHeader, TableCell } from '@tiptap/extension-table';
import Image from '@tiptap/extension-image';
import { TaskItem, TaskList } from '@tiptap/extension-list';
import { Underline } from '@tiptap/extension-underline';
import { Subscript } from '@tiptap/extension-subscript';
import { Superscript } from '@tiptap/extension-superscript';

/** Remove unsupported presentation markup from serialized tables. */
const PlainTable = Table.extend({
    renderHTML({ HTMLAttributes }) {
        return ['table', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes), ['tbody', 0]];
    },
});

/** Preserve marks with raw HTML because CommonMark has no equivalent syntax. */
const MarkdownUnderline = Underline.extend({
    markdownTokenizer: null,
    renderMarkdown(node, helpers) {
        return `<u>${helpers.renderChildren(node)}</u>`;
    },
});

const MarkdownSubscript = Subscript.extend({
    renderMarkdown(node, helpers) {
        return `<sub>${helpers.renderChildren(node)}</sub>`;
    },
});

const MarkdownSuperscript = Superscript.extend({
    renderMarkdown(node, helpers) {
        return `<sup>${helpers.renderChildren(node)}</sup>`;
    },
});

const CALLOUT_TYPES = ['note', 'tip', 'important', 'warning', 'caution'];

/** Require the marker on its own first line. */
const CALLOUT_MARKER = /^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\][ \t]*(?:\n|$)/;

/** Preserve GitHub-style callouts while plain readers see a blockquote. */
const Callout = Node.create({
    name: 'callout',

    // Parse callouts before ordinary blockquotes.
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
            setCallout:
                (attributes = {}) =>
                ({ commands }) =>
                    commands.wrapIn(this.name, normalize(attributes)),
            updateCalloutType:
                (attributes = {}) =>
                ({ commands }) =>
                    commands.updateAttributes(this.name, normalize(attributes)),
            unsetCallout:
                () =>
                ({ commands }) =>
                    commands.lift(this.name),
        };
    },

    markdownTokenName: 'blockquote',

    parseMarkdown(token, helpers) {
        const blockTokens = token.tokens || [];
        const firstBlock = blockTokens[0];

        // Let the ordinary blockquote parser handle all other shapes.
        if (!firstBlock || firstBlock.type !== 'paragraph') {
            return null;
        }

        const match = (firstBlock.text || '').match(CALLOUT_MARKER);

        if (!match) {
            return null;
        }

        const calloutType = match[1].toLowerCase();
        const remainder = (firstBlock.text || '').slice(match[0].length);

        // Remove the marker and keep content from the same paragraph.
        const restTokens = blockTokens.slice(1).filter((child) => child.type !== 'space');
        const childTokens = remainder.trim() !== ''
            ? [{ type: 'paragraph', text: remainder, tokens: helpers.tokenizeInline(remainder) }, ...restTokens]
            : restTokens;

        const content = helpers.parseBlockChildren(childTokens);

        return helpers.createNode(
            'callout',
            { calloutType },
            // The schema requires at least one block.
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

/** Build commands that both editor formats can serialize without loss. */
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
        { title: 'Subscript', keywords: ['sub'], run: ({ editor, range }) => at(editor, range).toggleSubscript().run() },
        { title: 'Superscript', keywords: ['super', 'sup'], run: ({ editor, range }) => at(editor, range).toggleSuperscript().run() },
        { title: 'Bulleted list', keywords: ['ul', 'bullet', 'unordered'], run: ({ editor, range }) => at(editor, range).toggleBulletList().run() },
        { title: 'Numbered list', keywords: ['ol', 'ordered', 'number'], run: ({ editor, range }) => at(editor, range).toggleOrderedList().run() },
        { title: 'Blockquote', keywords: ['quote', 'citation'], run: ({ editor, range }) => at(editor, range).toggleBlockquote().run() },
        { title: 'Inline code', keywords: ['code', 'mono'], run: ({ editor, range }) => at(editor, range).toggleCode().run() },
        { title: 'Code block', keywords: ['codeblock', 'pre', 'fenced'], run: ({ editor, range }) => at(editor, range).toggleCodeBlock().run() },
        { title: 'Link', keywords: ['url', 'href', 'a'], run: ({ editor, range }) => { at(editor, range).run(); onLink(); } },
        { title: 'Horizontal rule', keywords: ['hr', 'divider', 'rule'], run: ({ editor, range }) => at(editor, range).setHorizontalRule().run() },
        { title: 'Table', keywords: ['table', 'grid'], run: ({ editor, range }) => at(editor, range).insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run() },
        { title: 'Image', keywords: ['image', 'img', 'picture'], run: ({ editor, range }) => { at(editor, range).run(); onImage(); } },
        { title: 'Task list', keywords: ['todo', 'checklist', 'checkbox'], run: ({ editor, range }) => at(editor, range).toggleTaskList().run() },
        { title: 'Callout', keywords: ['note', 'tip', 'warning', 'alert', 'callout'], run: ({ editor, range }) => at(editor, range).setCallout({ type: 'note' }).run() },
    ];

    return items;
}

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

function slashExtension(format, onLink, onImage) {
    const menuItems = buildSlashItems(format, onLink, onImage);

    return Extension.create({
        name: 'slashCommands',
        addProseMirrorPlugins() {
            return [
                Suggestion({
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
            // MarkdownUnderline replaces the stock extension in both formats.
            underline: false,
        }),
        Placeholder.configure({ placeholder }),
        MarkdownUnderline,
        MarkdownSubscript,
        MarkdownSuperscript,
        // Image resize is lossy in Markdown.
        PlainTable,
        TableRow,
        TableHeader,
        TableCell,
        Image.configure({ inline: false, resize: isMarkdown ? false : { enabled: true } }),
        TaskItem,
        TaskList,
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
        // Alpine proxies break ProseMirror state. Keep the editor outside reactive data.
        let editor = null;

        return {
            ready: false,
            // Make toolbar active states reactive.
            tick: 0,

            init() {
                const textarea = this.$refs.textarea;
                const mount = this.$refs.editor;
                const isMarkdown = config.format === 'markdown';

                const syncTextarea = (instance) => {
                    // Preserve null and empty values.
                    if (instance.isEmpty) {
                        textarea.value = '';
                        return;
                    }
                    textarea.value = isMarkdown ? instance.getMarkdown() : instance.getHTML();
                };

                const notifyWordCount = (instance) => {
                    this.$el.dispatchEvent(new CustomEvent('wysiwyg:text-changed', {
                        detail: { text: instance.getText() },
                        bubbles: true,
                    }));
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
                            class: 'prose prose-sm font-manuscript max-w-none focus:outline-hidden px-3 py-2',
                            style: config.minHeight ? `min-height: ${config.minHeight}` : '',
                        },
                    },
                    onUpdate: ({ editor: instance }) => {
                        syncTextarea(instance);
                        notifyWordCount(instance);
                    },
                    onTransaction: () => {
                        this.tick++;
                    },
                });

                // Include the latest editor transaction in the submitted value.
                const form = this.$el.closest('form');
                if (form) {
                    form.addEventListener('submit', () => syncTextarea(editor));
                }

                this.ready = true;
            },

            destroy() {
                editor?.destroy();
            },

            cmd(name, arg) {
                if (!editor) return;
                editor.chain().focus()[name](arg).run();
            },

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

            setImage() {
                if (!editor) return;

                const url = window.prompt(config.imagePrompt || 'Enter an image URL (http:// or https://)');
                if (url === null || url === '') return; // cancelled or empty

                if (!/^https?:\/\//i.test(url)) return; // keep output within the allow-list

                const alt = window.prompt(config.imageAltPrompt || 'Alt text (optional, for accessibility)') || '';

                editor.chain().focus().setImage({ src: url, alt }).run();
            },

            setCalloutType(type) {
                if (!editor) return;

                if (editor.isActive('callout')) {
                    editor.chain().focus().updateCalloutType({ type }).run();
                    return;
                }

                editor.chain().focus().setCallout({ type }).run();
            },

            isOn(name, arg) {
                return this.tick >= 0 && !!editor && editor.isActive(name, arg);
            },
        };
    });
}
