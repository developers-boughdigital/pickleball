// The rich-text editor for article bodies. Loaded only on the article editor
// page (see articles.js), so the rest of the admin doesn't carry its weight.
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';

const escapeHtml = (text) => text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

/**
 * @param {HTMLElement} element where the editor goes
 * @param {string} html the saved body
 * @param {(html: string, words: number) => void} onChange called on every edit
 * @param {() => void} onMove called when the cursor moves, to refresh the toolbar
 */
export function mountEditor(element, html, onChange, onMove) {
    const editor = new Editor({
        element,
        content: html,
        extensions: [
            // h1 is the page title, so the body starts at h2.
            StarterKit.configure({
                heading: { levels: [2, 3, 4] },
                link: { openOnClick: false, autolink: true, defaultProtocol: 'https' },
            }),
            Placeholder.configure({ placeholder: 'Write the article here…' }),
        ],
        editorProps: {
            attributes: { class: 'article-body min-h-96 px-4 py-3 focus:outline-none', 'aria-label': 'Article body' },
        },
        onUpdate: ({ editor }) => {
            const text = editor.getText().trim();
            onChange(editor.isEmpty ? '' : editor.getHTML(), text ? text.split(/\s+/).length : 0);
        },
        onSelectionUpdate: onMove,
        onTransaction: onMove,
    });

    return {
        destroy: () => editor.destroy(),

        // What the toolbar highlights.
        active: () => ({
            bold: editor.isActive('bold'),
            italic: editor.isActive('italic'),
            underline: editor.isActive('underline'),
            h2: editor.isActive('heading', { level: 2 }),
            h3: editor.isActive('heading', { level: 3 }),
            bullets: editor.isActive('bulletList'),
            numbers: editor.isActive('orderedList'),
            quote: editor.isActive('blockquote'),
            link: editor.isActive('link'),
        }),

        run(command) {
            const chain = editor.chain().focus();

            ({
                bold: () => chain.toggleBold(),
                italic: () => chain.toggleItalic(),
                underline: () => chain.toggleUnderline(),
                h2: () => chain.toggleHeading({ level: 2 }),
                h3: () => chain.toggleHeading({ level: 3 }),
                bullets: () => chain.toggleBulletList(),
                numbers: () => chain.toggleOrderedList(),
                quote: () => chain.toggleBlockquote(),
                rule: () => chain.setHorizontalRule(),
                undo: () => chain.undo(),
                redo: () => chain.redo(),
            })[command]().run();
        },

        link() {
            const current = editor.getAttributes('link').href ?? '';
            const href = window.prompt('Link address. Leave it empty to remove the link.', current);
            if (href === null) return;

            const chain = editor.chain().focus().extendMarkRange('link');
            (href.trim() ? chain.setLink({ href: href.trim() }) : chain.unsetLink()).run();
        },

        // Each line becomes its own paragraph, which is how the site expects a shortcode.
        insertLines(lines) {
            editor.chain().focus().insertContent(lines.map((line) => `<p>${escapeHtml(line)}</p>`).join('')).run();
        },
    };
}
