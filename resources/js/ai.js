import Alpine from 'alpinejs';

/*
 * Claude features (resources/views/ai/*). Every endpoint answers JSON: the
 * result, or { message } with 409 (won't work: feature off, unconfigured,
 * declined) / 503 (may work on retry) / 429 (rate limit). Soft navigation
 * tears components down with the old <body>, so pollers clear their timers
 * in destroy().
 */

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export async function aiFetch(url, { method = 'POST', body = null } = {}) {
    let response;
    try {
        response = await fetch(url, {
            method,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                ...(body !== null ? { 'Content-Type': 'application/json' } : {}),
            },
            body: body !== null ? JSON.stringify(body) : null,
        });
    } catch (e) {
        return { ok: false, data: {}, error: 'Couldn’t reach the server. Check your connection and try again.' };
    }

    const data = await response.json().catch(() => ({}));
    if (response.ok) return { ok: true, data, error: '' };

    const error = response.status === 429
        ? 'Too many Claude requests. Wait a minute and try again.'
        : response.status === 422
            ? Object.values(data.errors ?? {}).flat()[0] ?? data.message ?? 'Please check the form.'
            : data.message ?? 'Claude couldn’t do this right now. Please try again.';

    return { ok: false, data, error };
}

const toast = (message, type = 'success') => window.dispatchEvent(new CustomEvent('toast', { detail: { type, message } }));

// Re-render the current page after a write (soft when Turbo is present).
const refresh = () => (window.Turbo ? window.Turbo.visit(window.location.href, { action: 'replace' }) : window.location.reload());

/*
 * One Claude request whose answer the template renders: x-data="aiPanel({ url })",
 * then run({ ...payload }) and read `result`, `busy`, `error`.
 */
Alpine.data('aiPanel', ({ url, initial = null } = {}) => ({
    busy: false,
    error: '',
    result: initial,

    async run(body = {}) {
        if (this.busy) return null;
        this.busy = true;
        this.error = '';
        const { ok, data, error } = await aiFetch(url, { body });
        this.busy = false;
        if (!ok) {
            this.error = error;
            return null;
        }
        this.result = data;
        return data;
    },

    reset() {
        this.result = null;
        this.error = '';
    },
}));

/*
 * Claude-proposed tasks the user reviews before anything is created.
 * propose() fills `items` (each gets `selected`), create() posts the chosen
 * ones to createUrl and refreshes the page.
 */
Alpine.data('aiSuggestions', ({ proposeUrl, createUrl, proposeBody = {} } = {}) => ({
    busy: false,
    creating: false,
    error: '',
    items: null,
    meta: {},

    get chosen() {
        return (this.items ?? []).filter((i) => i.selected);
    },

    async propose() {
        if (this.busy) return;
        this.busy = true;
        this.error = '';
        const { ok, data, error } = await aiFetch(proposeUrl, { body: proposeBody });
        this.busy = false;
        if (!ok) {
            this.error = error;
            return;
        }
        this.meta = data;
        this.items = (data.tasks ?? []).map((t) => ({ ...t, selected: true }));
    },

    async create() {
        if (this.creating || this.chosen.length === 0) return;
        this.creating = true;
        this.error = '';
        const tasks = this.chosen.map(({ selected, ...t }) => t);
        const { ok, data, error } = await aiFetch(createUrl, { body: { tasks } });
        this.creating = false;
        if (!ok) {
            this.error = error;
            return;
        }
        toast(data.message ?? 'Tasks created.');
        this.items = null;
        refresh();
    },

    discard() {
        this.items = null;
        this.error = '';
    },
}));

/*
 * A background result (release risk brief, standup digest, document
 * insights): start() requests it, then it polls statusUrl until it is ready
 * and swaps in the rendered card.
 */
const POLL_MS = 2500;

Alpine.data('aiInsight', ({ storeUrl, statusUrl, body = {}, insight = null } = {}) => ({
    status: insight?.status ?? null,
    error: insight?.status === 'failed' ? insight.error : '',
    busy: false,
    id: insight?.id ?? null,
    generated: insight?.generated_human ?? null,
    timer: null,
    stopped: false,

    get running() {
        return ['pending', 'processing'].includes(this.status);
    },

    init() {
        if (this.running) this.poll();
    },

    destroy() {
        this.stopped = true;
        clearTimeout(this.timer);
    },

    async start() {
        if (this.busy || this.running) return;
        this.busy = true;
        this.error = '';
        const { ok, data, error } = await aiFetch(storeUrl, { body });
        this.busy = false;
        if (!ok) {
            this.error = error;
            return;
        }
        this.apply(data);
        if (this.running) this.poll();
    },

    poll() {
        clearTimeout(this.timer);
        this.timer = setTimeout(async () => {
            if (this.stopped || !this.id) return;
            const { ok, data } = await aiFetch(statusUrl.replace('__ID__', this.id), { method: 'GET' });
            if (this.stopped) return;
            if (ok) this.apply(data);
            if (!ok || this.running) this.poll();
        }, POLL_MS);
    },

    apply(data) {
        this.id = data.id;
        this.status = data.status;
        this.generated = data.generated_human ?? this.generated;
        if (data.status === 'failed') this.error = data.error ?? 'Claude couldn’t finish this.';
        if (data.html && this.$refs.body) this.$refs.body.innerHTML = data.html;
    },
}));

/*
 * Release notes editor: draft(url, input) asks Claude for a draft and loads it
 * into the Trix editor bound to `input`; nothing is saved until the form is.
 */
Alpine.data('aiNotes', () => ({
    editing: false,
    busy: false,
    error: '',
    drafted: 0,

    async draft(url, input) {
        if (this.busy) return;
        this.busy = true;
        this.error = '';
        const { ok, data, error } = await aiFetch(url);
        this.busy = false;
        if (!ok) {
            this.error = error;
            return;
        }
        const editor = document.querySelector(`trix-editor[input="${input}"]`)?.editor;
        if (editor) {
            editor.recordUndoEntry('Draft with Claude');
            editor.loadHTML(data.html);
        }
        this.drafted = data.task_count ?? 0;
    },
}));

/*
 * Write into a Trix editor (by its hidden input id) or a plain field (by id)
 * from a Claude answer; used by the task writer and feedback drafts.
 */
export function fillField(id, value, html = false) {
    const trix = document.querySelector(`trix-editor[input="${id}"]`)?.editor;
    if (trix) {
        trix.recordUndoEntry('Claude');
        trix.loadHTML(html ? value : value.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c])).replace(/\n/g, '<br>'));
        return;
    }
    const el = document.getElementById(id);
    if (el) {
        el.value = value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

window.aiFillField = fillField;

/*
 * Add a draft after whatever the field already holds, never over it: `html` for
 * a Trix editor (by its hidden input id), plain text for any other field.
 */
export function appendField(id, value) {
    const trix = document.querySelector(`trix-editor[input="${id}"]`)?.editor;
    if (trix) {
        trix.recordUndoEntry('Claude');
        const end = trix.getDocument().getLength() - 1;
        trix.setSelectedRange([end, end]);
        trix.insertHTML(value);
        return;
    }
    const el = document.getElementById(id);
    if (el) {
        el.value = el.value.trim() ? `${el.value.trimEnd()}\n\n${value}` : value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

window.aiAppendField = appendField;

/*
 * The assignee suggestions dialog. Opened by an `ai-suggest-assignee` event with
 * { url, assignUrl, title, trigger }. Assigning goes through the board's endpoint;
 * on the board the card updates itself from `ai-assigned`, elsewhere the page refreshes.
 */
Alpine.data('aiAssignee', () => ({
    open: false,
    busy: false,
    error: '',
    title: '',
    assignUrl: '',
    trigger: null,
    suggestions: null,
    assigning: null,

    async show({ url, assignUrl, title, trigger }) {
        Object.assign(this, { open: true, busy: true, error: '', title, assignUrl, trigger, suggestions: null, assigning: null });
        const { ok, data, error } = await aiFetch(url);
        this.busy = false;
        if (ok) this.suggestions = data.suggestions ?? [];
        else this.error = error;
    },

    close() {
        this.open = false;
        this.trigger?.focus?.({ preventScroll: true });
    },

    async assign(person) {
        if (this.assigning) return;
        this.assigning = person.id;
        this.error = '';
        const { ok, data, error } = await aiFetch(this.assignUrl, { method: 'PATCH', body: { assignee_id: person.id } });
        this.assigning = null;
        if (!ok) {
            this.error = error;
            return;
        }
        const card = this.trigger?.closest('.board-card');
        this.open = false;
        if (card) card.dispatchEvent(new CustomEvent('ai-assigned', { bubbles: true, detail: data }));
        else {
            toast(`Assigned to ${person.name}.`);
            refresh();
        }
    },
}));

/* Ask your workspace: a running conversation of question → cited answer. */
Alpine.data('aiAsk', ({ url } = {}) => ({
    question: '',
    busy: false,
    error: '',
    turns: [],

    async ask(q = null) {
        const question = (q ?? this.question).trim();
        if (!question || this.busy) return;
        this.busy = true;
        this.error = '';
        this.question = '';
        this.turns.push({ question, answer: null });
        const { ok, data, error } = await aiFetch(url, { body: { question } });
        this.busy = false;
        const turn = this.turns[this.turns.length - 1];
        if (!ok) {
            this.error = error;
            this.turns.pop();
            this.question = question;
            return;
        }
        turn.answer = data;
        this.$nextTick(() => this.$refs.end?.scrollIntoView({ behavior: 'smooth', block: 'end' }));
    },
}));

window.aiFetch = aiFetch;

/*
 * Handing Claude a file, screenshot or text on the Requests page. A file can
 * come from the button, a drop anywhere on the page or the clipboard; it waits
 * for a click, so nothing paid for starts by accident. Plain text pasted outside
 * a field opens the text form; inside a field it pastes as usual.
 */
Alpine.data('requestImport', ({ extensions = [], imageExtensions = [], maxBytes = 0, maxImageBytes = 0, text = '', showText = false } = {}) => ({
    file: null,
    previewUrl: null,
    error: '',
    busy: false,
    dragging: false,
    text,
    showText,

    destroy() {
        this.clearPreview();
    },

    get extension() {
        return this.file?.name.split('.').pop() ?? '';
    },

    take(list) {
        const files = [...(list ?? [])];
        if (!files.length) return;

        const file = files[0];
        const extension = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';
        const image = imageExtensions.includes(extension);

        if (!extensions.includes(extension)) {
            return this.reject('Allowed types: PDF, PNG, JPG, GIF, WebP, DOCX, XLSX, CSV, TXT, MD.');
        }
        if (file.size > (image ? maxImageBytes : maxBytes)) {
            return this.reject(image ? 'Images may not be larger than 5 MB.' : 'The file may not be larger than 20 MB.');
        }

        const transfer = new DataTransfer();
        transfer.items.add(file);
        this.$refs.input.files = transfer.files;
        this.file = file;
        this.error = files.length > 1 ? `One file at a time, so this will read ${file.name}.` : '';
        this.clearPreview();
        if (image) this.previewUrl = URL.createObjectURL(file);
        this.$nextTick(() => this.$refs.send.focus());
    },

    reject(message) {
        this.clearFile();
        this.error = message;
    },

    clearFile() {
        this.file = null;
        this.error = '';
        this.$refs.input.value = '';
        this.clearPreview();
    },

    clearPreview() {
        if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
        this.previewUrl = null;
    },

    openText(value = null) {
        if (value !== null) this.text = value;
        this.showText = true;
        this.$nextTick(() => this.$refs.text.focus());
    },

    closeText() {
        this.showText = false;
        this.text = '';
    },

    size(bytes = 0) {
        return bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
    },

    onPaste(event) {
        const data = event.clipboardData;
        if (!data || this.busy) return;

        const files = [...data.files];
        const pasted = data.getData('text/plain').trim();
        // Copying from Word or Excel also offers a picture of the text, so text wins unless it is only the file's name.
        const isFile = files.length > 0 && (pasted === '' || files.some((f) => f.name === pasted));

        if (isFile) {
            event.preventDefault();
            this.take(files.map((f) => this.named(f)));
        } else if (pasted !== '' && !event.target.closest?.('input, textarea, select, [contenteditable="true"]')) {
            event.preventDefault();
            this.openText(data.getData('text/plain'));
        }
    },

    // Screenshots from the clipboard are all called image.png.
    named(file) {
        if (file.name && file.name !== 'image.png') return file;
        const stamp = new Date().toISOString().slice(0, 16).replace('T', ' ').replace(':', '.');

        return new File([file], `Screenshot ${stamp}.png`, { type: file.type });
    },

    hasFiles(event) {
        return [...(event.dataTransfer?.types ?? [])].includes('Files');
    },

    onDragEnter(event) {
        if (this.hasFiles(event)) this.dragging = true;
    },

    onDragOver(event) {
        if (this.hasFiles(event)) event.preventDefault();
    },

    // relatedTarget is empty only when the drag leaves the window.
    onDragLeave(event) {
        if (!event.relatedTarget) this.dragging = false;
    },

    onDrop(event) {
        this.dragging = false;
        if (!this.hasFiles(event)) return;
        event.preventDefault();
        if (!this.busy) this.take(event.dataTransfer.files);
    },
}));
