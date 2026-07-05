import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { Placeholder } from '@tiptap/extensions';

/**
 * The single, library-agnostic integration point for the WYSIWYG editor (Tiptap).
 * Everything else in the app talks to the editor only through the `x-wysiwyg` Blade
 * component and this Alpine component, so swapping libraries never touches a view.
 *
 * Progressive enhancement: the Blade component renders a real <textarea> holding the
 * HTML value. This component mounts the editor over it, hydrates from the textarea,
 * and syncs edits back into the textarea (on every change and again on submit) so the
 * ordinary, no-JS form submit carries the value and old() repopulates on failure.
 *
 * Slash-menu/toolbar output MUST stay within the task-01 allow-list in
 * App\Support\RichTextFields (p, h1–h4, strong, em, u, s, ul, ol, li, blockquote,
 * code, pre, a, br, hr; http/https schemes). StarterKit v3 bundles exactly those
 * nodes/marks; headings are capped at 1–4 and links restricted to http/https to keep
 * the two lists in sync. The server-side HtmlSanitizer is the real gate — this is
 * belt-and-braces so nothing the toolbar can produce gets stripped on save.
 */
export function registerWysiwyg(Alpine) {
    Alpine.data('wysiwyg', (config = {}) => ({
        editor: null,
        ready: false,
        // A monotonic counter bumped on every editor transaction. Toolbar bindings read
        // it (via isOn) so Alpine recomputes active states as the selection changes.
        tick: 0,

        init() {
            const textarea = this.$refs.textarea;
            const mount = this.$refs.editor;

            const syncTextarea = (editor) => {
                // Keep "empty" empty: getHTML() returns "<p></p>" for a blank doc, which
                // would defeat the nullable/empty handling on the server.
                textarea.value = editor.isEmpty ? '' : editor.getHTML();
            };

            this.editor = new Editor({
                element: mount,
                editable: !config.disabled,
                content: textarea.value || '',
                extensions: [
                    StarterKit.configure({
                        heading: { levels: [1, 2, 3, 4] },
                        link: {
                            openOnClick: false,
                            autolink: true,
                            protocols: ['http', 'https'],
                            HTMLAttributes: { rel: null, target: null },
                        },
                    }),
                    Placeholder.configure({
                        placeholder: config.placeholder || '',
                    }),
                ],
                editorProps: {
                    attributes: {
                        class: 'prose prose-sm max-w-none focus:outline-none px-3 py-2',
                        style: config.minHeight ? `min-height: ${config.minHeight}` : '',
                    },
                },
                onUpdate: ({ editor }) => syncTextarea(editor),
                onTransaction: () => {
                    this.tick++;
                },
            });

            // Belt-and-braces: sync once more on submit in case a change is mid-flight.
            const form = this.$el.closest('form');
            if (form) {
                form.addEventListener('submit', () => syncTextarea(this.editor));
            }

            this.ready = true;
        },

        destroy() {
            this.editor?.destroy();
        },

        /** Run a Tiptap chain command (e.g. 'toggleBold', 'toggleHeading') focused. */
        cmd(name, arg) {
            if (!this.editor) return;
            this.editor.chain().focus()[name](arg).run();
        },

        /** Toggle a link, prompting for an http/https URL (allow-list schemes only). */
        setLink() {
            if (!this.editor) return;

            if (this.editor.isActive('link')) {
                this.editor.chain().focus().unsetLink().run();
                return;
            }

            const previous = this.editor.getAttributes('link').href || '';
            const url = window.prompt(config.linkPrompt || 'Enter a URL (http:// or https://)', previous);

            if (url === null) return; // cancelled
            if (url === '') {
                this.editor.chain().focus().unsetLink().run();
                return;
            }
            if (!/^https?:\/\//i.test(url)) return; // keep output within the allow-list

            this.editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
        },

        /** Whether a mark/node is active at the cursor (reactive via `tick`). */
        isOn(name, arg) {
            return this.tick >= 0 && !!this.editor && this.editor.isActive(name, arg);
        },
    }));
}
