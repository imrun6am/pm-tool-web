import Alpine from 'alpinejs';
import * as Turbo from '@hotwired/turbo';

/*
 * Engagement features: the notification bell, the task-done celebration,
 * quick capture (press C) and the keyboard shortcut sheet (press ?).
 * Components here follow the soft-navigation rule from app.js: anything that
 * adds a timer or a window/document listener removes it in destroy().
 */

const QUICK_CAPTURE_PATH = '/tasks/new';

/** True while the user is typing somewhere a letter key belongs to the field. */
function isTyping(target) {
    return target instanceof Element
        && !!target.closest('input, textarea, select, trix-editor, [contenteditable]:not([contenteditable="false"])');
}

/** Whether a modal dialog (palette, confirm, drawer…) is currently on screen. */
function dialogOpen() {
    return [...document.querySelectorAll('[aria-modal="true"]')].some((el) => el.getClientRects().length > 0);
}

const toast = (type, message) => window.dispatchEvent(new CustomEvent('toast', { detail: { type, message } }));

/*
 * Celebration delivery for background requests (the board's drag-to-Done,
 * any fetch-based action): the server sends the message in an X-Celebrate
 * header (URL-encoded JSON). Redirecting requests get a session flash instead,
 * which layouts/toasts renders.
 */
if (!window.__celebrateFetchHook) {
    window.__celebrateFetchHook = true;
    const nativeFetch = window.fetch;
    window.fetch = async function (...args) {
        const response = await nativeFetch.apply(this, args);
        const raw = response.headers.get('X-Celebrate');
        if (raw) {
            try {
                toast('celebrate', JSON.parse(decodeURIComponent(raw)).message);
            } catch (e) {
                // Malformed header — the action itself still succeeded.
            }
        }
        return response;
    };
}

/*
 * Top-bar bell: the badge is server-rendered on every page; this re-checks the
 * unread count once a minute while the tab is visible.
 */
Alpine.data('notificationBell', (initial = 0, countUrl = '') => ({
    open: false,
    count: initial,
    timer: null,

    init() {
        this.timer = setInterval(() => this.refresh(), 60000);
    },

    destroy() {
        clearInterval(this.timer);
    },

    async refresh() {
        if (document.visibilityState !== 'visible' || !countUrl) return;
        try {
            const response = await fetch(countUrl, { headers: { Accept: 'application/json' } });
            if (response.ok) this.count = (await response.json()).count;
        } catch (e) {
            // Offline for a moment — keep the last known count.
        }
    },
}));

/*
 * Quick capture dialog + shortcut sheet (layouts/quick-capture). The form is
 * fetched fresh from /tasks/new each time it opens, so its pickers and CSRF
 * token are current; it is the same partial the full page renders.
 */
Alpine.data('quickCapture', () => ({
    modKey: /Mac|iPhone|iPad/.test(navigator.platform) ? '⌘' : 'Ctrl',
    open: false,
    shortcuts: false,
    loading: false,
    returnFocus: null,
    onBeforeVisit: null,
    onClick: null,

    init() {
        // "New task" links (sidebar Create menu, ⌘K entry) open the dialog
        // instead of navigating; with JavaScript off they reach the page.
        this.onBeforeVisit = (e) => {
            if (new URL(e.detail.url, location.href).pathname !== QUICK_CAPTURE_PATH) return;
            if (location.pathname === QUICK_CAPTURE_PATH) return;
            e.preventDefault();
            this.show();
        };
        document.addEventListener('turbo:before-visit', this.onBeforeVisit);
    },

    destroy() {
        document.removeEventListener('turbo:before-visit', this.onBeforeVisit);
    },

    onKeydown(e) {
        if (e.metaKey || e.ctrlKey || e.altKey || e.defaultPrevented || isTyping(e.target)) return;
        if (this.open || this.shortcuts || dialogOpen()) return;

        if (e.key === 'c' || e.key === 'C') {
            if (e.shiftKey || location.pathname === QUICK_CAPTURE_PATH) return;
            e.preventDefault();
            this.show();
        } else if (e.key === '?') {
            e.preventDefault();
            this.returnFocus = document.activeElement;
            this.shortcuts = true;
            this.$nextTick(() => this.$refs.shortcutsClose?.focus());
        }
    },

    async show() {
        if (this.open || this.loading) return;
        this.returnFocus = document.activeElement;
        this.loading = true;
        try {
            const response = await fetch(QUICK_CAPTURE_PATH, { headers: { 'X-Quick-Capture': '1', Accept: 'text/html' } });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const body = this.$refs.body;
            [...body.children].forEach((el) => Alpine.destroyTree(el));
            body.innerHTML = await response.text();
            this.open = true;
            this.$nextTick(() => body.querySelector('[autofocus], input[name="title"]')?.focus());
        } catch (e) {
            Turbo.visit(QUICK_CAPTURE_PATH);
        } finally {
            this.loading = false;
        }
    },

    close() {
        this.open = false;
        this.returnFocus?.focus?.();
    },

    closeShortcuts() {
        this.shortcuts = false;
        this.returnFocus?.focus?.();
    },

    /** Called by the form after a successful save. */
    saved(message) {
        this.close();
        // Pages that list tasks pick the new one up in place. The refresh swaps
        // <body> (and the toaster with it), so the toast waits for the new page.
        if (['/board', '/dashboard'].includes(location.pathname)) {
            document.addEventListener('turbo:load', () => toast('success', message), { once: true });
            Turbo.visit(location.href, { action: 'replace' });
        } else {
            toast('success', message);
        }
    },
}));

/*
 * The capture form itself (tasks/_quick-form): due-date chips, and — inside
 * the dialog — a background submit with inline validation errors.
 */
Alpine.data('quickTaskForm', (inDialog = false) => ({
    due: '',
    busy: false,
    errors: {},

    /** YYYY-MM-DD for today plus `days`, in the browser's time zone. */
    dateIn(days) {
        const date = new Date();
        date.setDate(date.getDate() + days);
        return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
    },

    /** Days from today to next week's Monday (2–8, so it never equals Tomorrow). */
    nextWeek() {
        const days = ((8 - new Date().getDay()) % 7) || 7;
        return days === 1 ? 8 : days;
    },

    setDue(days) {
        this.due = days === null ? '' : this.dateIn(days);
    },

    isDue(days) {
        return days === null ? this.due === '' : this.due === this.dateIn(days);
    },

    async submit(e) {
        if (!inDialog) return; // the full page posts normally
        e.preventDefault();
        if (this.busy) return;
        this.busy = true;
        this.errors = {};
        const form = e.target;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
                body: new FormData(form),
            });
            const data = await response.json().catch(() => ({}));
            if (response.status === 422) {
                this.errors = Object.fromEntries(Object.entries(data.errors || {}).map(([k, v]) => [k, v[0]]));
                this.$nextTick(() => form.querySelector('[aria-invalid="true"]')?.focus());
                return;
            }
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            this.$dispatch('quick-capture-saved', { message: data.message });
        } catch (err) {
            this.errors = { title: 'Couldn’t save the task. Please try again.' };
        } finally {
            this.busy = false;
        }
    },
}));
