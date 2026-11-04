import Alpine from 'alpinejs';
import { aiFetch } from './ai';
import '../css/assistant.css';

/*
 * The assistant's chat, loaded on first open. A message answers NDJSON as it
 * goes: `status` (what it's looking at), `text` (the answer as it's written),
 * `reset` (a fallback model took over), `action` (a draft to confirm), `error`,
 * and `done`, whose items replace everything shown so far with the rendered,
 * cited version. Confirming sends only the draft's id; the server decides what
 * runs.
 */
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const WAITING_RETRIES = 4;
const WAITING_MS = 2500;

// A list page that shows what was just changed. Forms are left alone, so typing isn't lost.
const LIST_PAGE = /^\/(calendar|board|tasks|notes)\/?$/;

const failureMessage = (status, data) => {
    if (status === 429) return 'That’s a lot of requests. Wait a minute and try again.';
    if (status === 401 || status === 419) return 'Your session has ended. Reload the page and sign in again.';
    if (status === 422) return Object.values(data.errors ?? {}).flat()[0] ?? data.message ?? 'That message can’t be sent.';

    return data.message ?? 'The assistant couldn’t answer. Try again.';
};

// Citations become links in `done`; while streaming, they'd only be noise.
const withoutRefs = (text) => text.replace(/\s?\[[A-Z]\d+\]/g, '');

Alpine.data('assistantChat', ({ urls, available = true }) => ({
    items: [],
    live: null,
    draft: '',
    loaded: false,
    sending: false,
    resolving: false,
    error: '',
    retryable: false,
    lastMessage: '',
    pinned: true,
    waitingTries: 0,

    get pendingActions() {
        return [
            ...this.items.filter((i) => i.type === 'action').map((i) => i.action),
            ...(this.live?.actions ?? []),
        ].filter((a) => a.status === 'pending');
    },

    get pendingCount() {
        return this.pendingActions.length;
    },

    init() {
        this.load();
        this.$watch('panelOpen', (open) => {
            if (open) this.$nextTick(() => this.scrollToEnd(true));
        });
    },

    async load() {
        const { ok, data } = await aiFetch(urls.conversation, { method: 'GET' });
        this.loaded = true;
        if (!ok) {
            if (available) this.showError('Your conversation didn’t load. Check your connection.', false);
            return;
        }
        this.items = data.items ?? [];
        this.$nextTick(() => {
            this.scrollToEnd(true);
            this.focusInput();
        });
        this.awaitAnswer();
    },

    // A full page load mid-turn: the server finishes it, so look again for the answer.
    awaitAnswer() {
        if (this.items.at(-1)?.type !== 'user' || this.waitingTries >= WAITING_RETRIES) {
            this.waitingTries = 0;
            return;
        }
        this.waitingTries++;
        setTimeout(async () => {
            if (this.sending) return;
            const { ok, data } = await aiFetch(urls.conversation, { method: 'GET' });
            if (ok && !this.sending) {
                this.items = data.items ?? [];
                this.$nextTick(() => this.scrollToEnd());
            }
            this.awaitAnswer();
        }, WAITING_MS);
    },

    focusInput() {
        if (window.matchMedia('(min-width: 640px)').matches) this.$refs.input?.focus({ preventScroll: true });
    },

    start(text) {
        this.draft = text;
        this.$nextTick(() => {
            this.$refs.input?.focus();
            this.$refs.input?.setSelectionRange(text.length, text.length);
            this.resize();
        });
    },

    onEnter(event) {
        if (event.shiftKey || event.isComposing) return;
        event.preventDefault();
        this.send();
    },

    resize() {
        const el = this.$refs.input;
        if (!el) return;
        el.style.height = 'auto';
        el.style.height = `${Math.min(el.scrollHeight, 128)}px`;
    },

    trackScroll() {
        const el = this.$refs.log;
        this.pinned = el.scrollHeight - el.scrollTop - el.clientHeight < 40;
    },

    scrollToEnd(force = false) {
        const el = this.$refs.log;
        if (el && (force || this.pinned)) el.scrollTop = el.scrollHeight;
    },

    showError(message, retryable) {
        this.error = message;
        this.retryable = retryable;
        this.$nextTick(() => this.scrollToEnd(true));
    },

    clearError() {
        this.error = '';
        this.retryable = false;
    },

    // A link in the full-screen sheet leads away, so get out of its way.
    onLogClick(event) {
        if (event.target.closest?.('a') && !window.matchMedia('(min-width: 640px)').matches) this.close();
    },

    async send(text = null) {
        const message = (text ?? this.draft).trim();
        if (!message || this.sending) return;

        this.clearError();
        this.lastMessage = message;
        this.draft = '';
        this.$nextTick(() => this.resize());
        this.items.push({ type: 'user', key: `sent-${Date.now()}`, text: message });
        // Sending replaces open drafts; the server's answer redraws them as cancelled.
        this.items.filter((i) => i.type === 'action' && i.action.status === 'pending').forEach((i) => { i.action.status = 'cancelled'; });
        this.pinned = true;

        const ok = await this.stream(urls.message, { message, page: location.pathname });
        if (!ok) {
            // It wasn't kept on the server, so put it back to retry or edit.
            this.items = this.items.filter((i) => !(i.type === 'user' && String(i.key).startsWith('sent-')));
            this.draft = this.draft || message;
            this.$nextTick(() => this.resize());
        }
    },

    retry() {
        const message = this.draft.trim() || this.lastMessage;
        this.clearError();
        this.draft = '';
        this.send(message);
    },

    /** Run one streamed turn. Answers whether it was answered at all. */
    async stream(url, body) {
        this.sending = true;
        this.working = true;
        this.live = { text: '', raw: '', status: 'Thinking', actions: [] };
        this.$nextTick(() => this.scrollToEnd(true));

        let answered = false;
        let settled = false;
        let failed = false;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { Accept: 'application/x-ndjson', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify(body),
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                this.showError(failureMessage(response.status, data), response.status === 429);
                failed = true;
            } else {
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                for (;;) {
                    const { done, value } = await reader.read();
                    buffer += decoder.decode(value ?? new Uint8Array(), { stream: !done });
                    const lines = buffer.split('\n');
                    buffer = lines.pop();
                    for (const line of lines) {
                        if (!line.trim()) continue;
                        const event = JSON.parse(line);
                        if (event.type === 'done') settled = true;
                        else if (event.type === 'text' || event.type === 'action') answered = true;
                        else if (event.type === 'error') failed = !answered;
                        this.apply(event);
                    }
                    if (done) break;
                }

                if (!settled) throw new Error('The answer stopped early.');
            }
        } catch (e) {
            this.showError('The connection dropped before the answer finished.', false);
            // The server may have finished regardless, so show what it has.
            const { ok, data } = await aiFetch(urls.conversation, { method: 'GET' });
            if (ok) {
                this.items = data.items ?? [];
                answered = true;
            }
        }

        this.sending = false;
        this.working = false;
        this.live = null;
        if (!this.panelOpen) this.attention = true;
        this.$nextTick(() => this.scrollToEnd());

        return !failed || answered;
    },

    apply(event) {
        const live = this.live;
        switch (event.type) {
            case 'status':
                live.status = event.label;
                break;
            case 'text':
                live.raw += event.delta;
                live.text = withoutRefs(live.raw);
                live.status = 'Writing';
                break;
            case 'reset':
                live.raw = '';
                live.text = '';
                break;
            case 'action':
                live.actions.push(event.action);
                live.status = 'Preparing';
                break;
            case 'error':
                this.showError(event.message, event.retryable);
                break;
            case 'done':
                this.items = event.items;
                break;
        }
        this.$nextTick(() => this.scrollToEnd());
    },

    /** Replace a draft wherever it's shown with the server's version of it. */
    update(action, fresh) {
        Object.assign(action, fresh, { busy: false, localError: '' });
        this.items.forEach((item) => {
            if (item.type === 'action' && item.action.id === fresh.id) item.action = { ...item.action, ...fresh, busy: false, localError: '' };
        });
    },

    async resolve(action, url, kind) {
        if (action.busy) return null;
        action.busy = kind;
        action.localError = '';
        const { ok, data, error } = await aiFetch(url);
        action.busy = false;
        if (!ok) {
            action.localError = error;
            return null;
        }
        this.update(action, data.action);

        return data;
    },

    async confirm(action) {
        const data = await this.resolve(action, action.confirm_url, 'confirm');
        if (!data) return;
        this.focusInput();
        this.afterChange(action);
        if (data.follow_up) await this.stream(urls.continue, {});
    },

    async confirmAll() {
        if (this.resolving) return;
        this.resolving = true;
        let followUp = false;
        for (const action of [...this.pendingActions]) {
            const data = await this.resolve(action, action.confirm_url, 'confirm');
            if (data) {
                this.afterChange(action);
                followUp = followUp || data.follow_up;
            }
        }
        this.resolving = false;
        this.focusInput();
        if (followUp) await this.stream(urls.continue, {});
    },

    async cancel(action) {
        await this.resolve(action, action.cancel_url, 'cancel');
        this.focusInput();
    },

    // Show the new record if the page behind the chat lists it.
    afterChange(action) {
        if (action.status === 'done' && LIST_PAGE.test(location.pathname) && window.Turbo) {
            window.Turbo.visit(location.href, { action: 'replace' });
        }
    },

    async newChat() {
        if (this.sending) return;
        const { ok } = await aiFetch(this.$root.dataset.resetUrl, { method: 'DELETE' });
        if (ok) {
            this.items = [];
            this.clearError();
            this.focusInput();
        }
    },
}));
