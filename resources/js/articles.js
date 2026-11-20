import { visit } from './router';

/**
 * The article editor form: the rich-text body, live counters, and the image preview.
 * The body's saved HTML lives in a <textarea x-ref="body">; if the editor can't
 * load, that textarea stays on screen and the article can still be edited.
 */
export const articleEditor = ({ title, metaTitle, metaText, image }) => {
    // Kept out of the component on purpose: Alpine would wrap the editor in proxies and break it.
    let editor = null;

    return {
        dirty: false,
        ready: false,
        title: title ?? '',
        metaTitle: metaTitle ?? '',
        metaText: metaText ?? '',
        image,
        removeImage: false,
        words: 0,
        active: {},
        tagFilter: '',

        async init() {
            try {
                const { mountEditor } = await import('./editor');
                const body = this.$refs.body;

                editor = mountEditor(
                    this.$refs.editor,
                    body.value,
                    (html, words) => {
                        body.value = html;
                        this.words = words;
                        this.dirty = true;
                    },
                    () => editor && (this.active = editor.active()),
                );
                this.words = body.value ? this.$refs.editor.innerText.trim().split(/\s+/).filter(Boolean).length : 0;
                this.ready = true;
            } catch {
                // Leave the plain textarea in place.
            }
        },

        destroy() {
            editor?.destroy();
            editor = null;
        },

        run(command) {
            editor?.run(command);
        },

        link() {
            editor?.link();
        },

        insert(lines) {
            if (editor) {
                editor.insertLines(lines);
            } else {
                this.$refs.body.value += `\n${lines.map((line) => `<p>${line}</p>`).join('\n')}`;
            }
            this.dirty = true;
        },

        pickImage(event) {
            const [file] = event.target.files;
            if (!file) return;

            URL.revokeObjectURL(this.image);
            this.image = URL.createObjectURL(file);
            this.removeImage = false;
        },

        clearImage() {
            this.image = null;
            this.removeImage = true;
            this.$refs.image.value = '';
            this.dirty = true;
        },

        // Hides tags that don't match what's typed in the filter box.
        tagMatches(path) {
            return !this.tagFilter || path.toLowerCase().includes(this.tagFilter.toLowerCase());
        },
    };
};

/**
 * "Claude is writing": posts to the write URL and shows the draft arriving.
 * The server sends one JSON object per line: {type: status|text|error|done, text}.
 */
export const draftWriter = ({ url, token, auto }) => {
    let request = null;

    return {
        state: auto ? 'starting' : 'watching', // starting, writing, watching (another tab owns it), failed
        steps: [],
        text: '',
        error: '',
        seconds: 0,
        timer: null,

        init() {
            this.timer = setInterval(() => this.seconds++, 1000);
            auto ? this.start() : this.checkBackSoon();
        },

        destroy() {
            clearInterval(this.timer);
            clearTimeout(this.recheck);
            // Only stops listening. The server finishes the draft and saves it regardless.
            request?.abort();
        },

        get headline() {
            return this.text.match(/^TITLE:[ \t]*(.+)$/m)?.[1] ?? '';
        },

        // The article itself is everything after the "---" line.
        get article() {
            const start = this.text.search(/\n-{3,}[ \t]*\n/);
            return start === -1 ? '' : this.text.slice(start).replace(/^\n-+[ \t]*\n/, '');
        },

        get wordCount() {
            return this.article.trim() ? this.article.trim().split(/\s+/).length : 0;
        },

        get clock() {
            return `${Math.floor(this.seconds / 60)}:${String(this.seconds % 60).padStart(2, '0')}`;
        },

        async start() {
            request = new AbortController();

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, Accept: 'application/x-ndjson' },
                    signal: request.signal,
                });

                // Another tab got there first: just wait for it.
                if (response.status === 409) {
                    this.state = 'watching';
                    return this.checkBackSoon();
                }
                if (!response.ok) throw new Error(`HTTP ${response.status}`);

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                for (;;) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop();
                    lines.filter(Boolean).forEach((line) => this.handle(JSON.parse(line)));
                }

                // The stream ended without "done" or "error": the server was cut off.
                if (this.state !== 'failed' && this.state !== 'done') {
                    this.fail('The connection closed before the draft was finished. Open the article again in a minute: it may have been saved.');
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    this.fail('The connection dropped. Claude may still be finishing; open the article again in a minute.');
                }
            }
        },

        handle({ type, text }) {
            if (type === 'status') {
                this.steps.push(text);
            } else if (type === 'text') {
                this.state = 'writing';
                this.text += text;
                this.$nextTick(() => this.$refs.draft && (this.$refs.draft.scrollTop = this.$refs.draft.scrollHeight));
            } else if (type === 'error') {
                this.fail(text);
            } else if (type === 'done') {
                this.state = 'done';
                clearInterval(this.timer);
                visit(text);
            }
        },

        fail(message) {
            this.state = 'failed';
            this.error = message;
            clearInterval(this.timer);
        },

        checkBackSoon() {
            this.recheck = setTimeout(() => visit(window.location.href, { push: false }), 8000);
        },
    };
};
