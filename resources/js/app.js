import Alpine from 'alpinejs';
import * as Turbo from '@hotwired/turbo';
import 'trix';
import './tasksheet-exports';
import './engagement';
import './searchable-select';
import './ai';

window.Alpine = Alpine;

/*
 * Soft navigation (Turbo Drive): same-origin links and forms fetch the next
 * page and swap <body> in place, so there's no blank flash between pages.
 * Alpine's mutation observer boots the new body's components, but it only
 * tears down removed nodes it initialized itself — never <body> — so the old
 * body is destroyed explicitly once it's swapped out (running destroy()
 * hooks and x-on .window/.document cleanups). Any component that adds a
 * listener to window/document/a media query must remove it in destroy().
 * Declare the field that holds the teardown in the component's data: an
 * undeclared property set from a nested component lands on the outermost
 * scope (appShell), where sibling instances would overwrite each other's.
 * Opt a link or form out with data-turbo="false" (downloads, logout).
 */
Turbo.config.drive.progressBarDelay = 0;

let outgoingBody = null;

document.addEventListener('turbo:before-render', (e) => {
    outgoingBody = document.body;
    // A same-page refresh (an action that redirected back here) keeps its
    // scroll position; don't let Turbo's [autofocus] handling jump away.
    const visit = Turbo.session.navigator.currentVisit;
    const refresh = !!(visit && Turbo.session.view.isPageRefresh?.(visit));
    if (refresh) {
        e.detail.newBody.querySelectorAll('[autofocus]').forEach((el) => el.removeAttribute('autofocus'));
    }
    // The page-change animation (app.css, view transitions) is for moving
    // between pages; a refresh of the same page just swaps in place.
    document.documentElement.dataset.vt = refresh ? 'refresh' : 'visit';
    // `scroll-behavior: smooth` on <html> would animate Turbo's jump to the
    // top (or its back/forward restore); switch it off for the swap.
    document.documentElement.style.scrollBehavior = 'auto';
});
document.addEventListener('turbo:render', () => {
    if (outgoingBody && outgoingBody !== document.body) Alpine.destroyTree(outgoingBody);
    outgoingBody = null;
});
document.addEventListener('turbo:load', () => {
    revealInvalid(document);
    requestAnimationFrame(() => document.documentElement.style.removeProperty('scroll-behavior'));
});

// After a failed submit (same-page refreshes keep scroll), bring the first
// invalid field (marked by @invalid) into view so the error isn't off-screen.
function revealInvalid(root) {
    const field = root.querySelector('[aria-invalid="true"]');
    if (!field) return;
    field.scrollIntoView({ block: 'nearest' });
    field.focus({ preventScroll: true });
}

/*
 * In-page actions (<x-live-region>, a Turbo Frame with target="_top"): any
 * non-GET form inside a region submits in the background and only that
 * region is replaced, cut from the page the action redirects back to — so
 * controllers keep plain back()->with(...) responses. Links and GET filters
 * inside it still navigate the whole page. A form can opt out with
 * data-turbo-frame="_top".
 */
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || form.method === 'get' || form.dataset.turboFrame) return;
    const region = form.closest('turbo-frame[data-live-region]');
    if (region) form.dataset.turboFrame = region.id;
}, true);

// Turbo only drives the top progress bar for whole-page visits; run it for
// region submissions too.
let regionPending = false;
const progressBar = () => Turbo.session.adapter.progressBar;
const settleRegion = () => {
    if (!regionPending) return;
    regionPending = false;
    progressBar().setValue(1);
    progressBar().hide();
};
document.addEventListener('turbo:submit-start', (e) => {
    const target = e.target.dataset.turboFrame;
    if (!target || target === '_top') return;
    regionPending = true;
    progressBar().setValue(0);
    progressBar().show();
});
document.addEventListener('turbo:submit-end', (e) => {
    if (!e.detail.success) settleRegion();
});

// Tear down the region's Alpine components before its content is replaced
// (a region outside any x-data root would otherwise leak them).
document.addEventListener('turbo:before-frame-render', (e) => {
    const render = e.detail.render;
    e.detail.render = async (current, next) => {
        [...current.children].forEach((el) => Alpine.destroyTree(el));
        await render(current, next);
    };
});

// The toaster lives outside the region, so raise the response's flash toasts
// (layouts/toasts renders them as #flash-toasts) by hand.
document.addEventListener('turbo:frame-render', async (e) => {
    settleRegion();
    revealInvalid(e.target);
    const html = await e.detail.fetchResponse.responseHTML;
    if (!html) return;
    const payload = new DOMParser().parseFromString(html, 'text/html').getElementById('flash-toasts');
    try {
        JSON.parse(payload?.textContent || '[]').forEach((t) => window.dispatchEvent(new CustomEvent('toast', { detail: t })));
    } catch (err) {
        // Malformed payload — the region still updated; skip the toasts.
    }
});

// The action redirected to a page without this region (e.g. a delete that
// lands on an index): show that page as a normal soft visit instead.
document.addEventListener('turbo:frame-missing', (e) => {
    e.preventDefault();
    settleRegion();
    e.detail.visit(e.detail.response);
});

const SIDEBAR_KEY = 'rp.sidebar';
const desktop = window.matchMedia('(min-width: 1024px)');

/*
 * App shell: mobile drawer + collapsible desktop sidebar. The collapsed state
 * lives on <html data-sidebar="collapsed"> (set pre-paint by the layout) so CSS
 * owns the widths; this only flips it and remembers the choice.
 */
Alpine.data('appShell', () => ({
    mobileOpen: false,
    collapsed: document.documentElement.dataset.sidebar === 'collapsed',
    modKey: /Mac|iPhone|iPad/.test(navigator.platform) ? '⌘' : 'Ctrl',
    tip: { show: false, text: '', top: 0 },
    stopListening: null,

    init() {
        this.$watch('mobileOpen', (open) => {
            document.documentElement.classList.toggle('overflow-hidden', open);
            this.$nextTick(() => (open ? this.$refs.sidebarClose : this.$refs.menuButton)?.focus());
        });
        const listeners = new AbortController();
        desktop.addEventListener('change', (e) => {
            if (e.matches) this.mobileOpen = false;
        }, { signal: listeners.signal });
        this.stopListening = () => listeners.abort();
    },

    destroy() {
        this.stopListening?.();
        // Navigating from the open mobile drawer must not leave the page scroll-locked.
        document.documentElement.classList.remove('overflow-hidden');
    },

    closeMobile() {
        this.mobileOpen = false;
    },

    toggleCollapse() {
        this.collapsed = !this.collapsed;
        this.hideTip();
        const root = document.documentElement;
        if (this.collapsed) root.dataset.sidebar = 'collapsed';
        else delete root.dataset.sidebar;
        try {
            localStorage.setItem(SIDEBAR_KEY, this.collapsed ? 'collapsed' : 'expanded');
        } catch (e) {
            // Storage blocked (private mode etc.) — the choice just won't persist.
        }
    },

    // Rail tooltips: only while collapsed on desktop; text is the control's label.
    showTip(el) {
        if (!this.collapsed || !desktop.matches) return;
        const label = el.querySelector('.nav-label');
        const rect = el.getBoundingClientRect();
        this.tip = { show: true, text: label ? label.textContent.trim() : '', top: rect.top + rect.height / 2 };
    },

    hideTip() {
        this.tip.show = false;
    },
}));

/*
 * ⌘K / Ctrl+K command palette over the user's (role-filtered) navigation
 * entries. `results` holds indices into `entries`; the list itself is
 * server-rendered so icons come from <x-icon>. From two characters on, it
 * also lists matching tasks (`tasks`, from `searchUrl`) after the pages;
 * `active` runs over pages first, then tasks.
 */
Alpine.data('commandPalette', (entries = [], searchUrl = '') => ({
    entries,
    open: false,
    query: '',
    active: 0,
    returnFocus: null,
    tasks: [],
    searchSeq: 0,
    searchTimer: null,

    get results() {
        const q = this.query.trim().toLowerCase();
        return this.entries
            .map((entry, i) => i)
            .filter((i) => q === '' || `${this.entries[i].label} ${this.entries[i].group}`.toLowerCase().includes(q));
    },

    get total() {
        return this.results.length + this.tasks.length;
    },

    init() {
        this.$watch('query', () => {
            this.active = 0;
            this.searchTasks();
        });
    },

    destroy() {
        clearTimeout(this.searchTimer);
    },

    /** Debounced task search; a stale response never overwrites a newer one. */
    searchTasks() {
        clearTimeout(this.searchTimer);
        const q = this.query.trim();
        const seq = ++this.searchSeq;
        if (!searchUrl || q.length < 2) {
            this.tasks = [];
            return;
        }
        this.searchTimer = setTimeout(async () => {
            try {
                const response = await fetch(`${searchUrl}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const data = await response.json();
                if (seq === this.searchSeq) this.tasks = data.data;
            } catch (e) {
                // Search is a convenience; page results still work.
            }
        }, 150);
    },

    optionId(position) {
        return position < this.results.length
            ? `cmdk-opt-${this.results[position]}`
            : `cmdk-task-${position - this.results.length}`;
    },

    onKeydown(e) {
        if (!(e.metaKey || e.ctrlKey) || e.altKey || e.shiftKey || e.key.toLowerCase() !== 'k') return;
        // Leave ⌘K to rich-text editors (Trix binds it to "insert link").
        if (e.defaultPrevented) return;
        if (e.target instanceof Element && e.target.closest('trix-editor, [contenteditable]:not([contenteditable="false"])')) return;
        e.preventDefault();
        this.open ? this.close() : this.show();
    },

    show() {
        this.returnFocus = document.activeElement;
        this.query = '';
        this.active = 0;
        this.tasks = [];
        this.open = true;
        this.$nextTick(() => this.$refs.input.focus());
    },

    close() {
        this.open = false;
        this.returnFocus?.focus?.();
    },

    move(step) {
        const count = this.total;
        if (!count) return;
        this.active = (this.active + step + count) % count;
        this.$nextTick(() => document.getElementById(this.optionId(this.active))?.scrollIntoView({ block: 'nearest' }));
    },

    go(position = this.active) {
        const target = position < this.results.length
            ? this.entries[this.results[position]]
            : this.tasks[position - this.results.length];
        if (!target) return;
        // Close first: an entry may open a dialog instead of navigating (New task).
        this.close();
        Turbo.visit(target.url);
    },
}));

/*
 * Filter bar (x-filter-bar): selects/dates apply on change; search applies on
 * Enter or after a typing pause, and keeps focus across the reload so typing
 * can continue.
 */
const FILTER_FOCUS_KEY = 'rp.filter-focus';

Alpine.data('filterBar', () => ({
    init() {
        const search = this.$root.querySelector('input[type="search"]');
        if (!search) return;

        try {
            if (sessionStorage.getItem(FILTER_FOCUS_KEY) === '1') {
                sessionStorage.removeItem(FILTER_FOCUS_KEY);
                search.focus();
                search.setSelectionRange(search.value.length, search.value.length);
            }
        } catch (e) {
            // Storage unavailable — focus just isn't restored.
        }

        const initial = search.value;
        let timer;
        search.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                if (search.value === initial) return;
                try {
                    sessionStorage.setItem(FILTER_FOCUS_KEY, '1');
                } catch (e) {}
                this.submit();
            }, 450);
        });
    },

    onChange(e) {
        // Search applies via Enter / the typing pause, not on blur.
        if (e.target.type === 'search') return;
        this.submit();
    },

    submit() {
        const form = this.$root;
        form.requestSubmit ? form.requestSubmit() : form.submit();
    },
}));

/*
 * Settings section nav: highlights the section crossing the middle of the
 * viewport (and whatever the URL hash or a click points at).
 */
Alpine.data('settingsNav', (ids = []) => ({
    active: ids[0] ?? null,
    stopListening: null,

    init() {
        const fromHash = location.hash.slice(1);
        if (ids.includes(fromHash)) this.active = fromHash;
        if (!('IntersectionObserver' in window)) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) this.active = entry.target.id;
            });
        }, { rootMargin: '-40% 0px -55% 0px' });

        ids.forEach((id) => {
            const el = document.getElementById(id);
            if (el) observer.observe(el);
        });

        // The last section may never reach the middle band on tall screens.
        const listeners = new AbortController();
        window.addEventListener('scroll', () => {
            const atBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4;
            if (atBottom && ids.length) this.active = ids[ids.length - 1];
        }, { passive: true, signal: listeners.signal });

        this.stopListening = () => {
            observer.disconnect();
            listeners.abort();
        };
    },

    destroy() {
        this.stopListening?.();
    },
}));

/*
 * "Enhance with Claude" (x-ai-enhance): sends a Trix editor's content to the
 * server and swaps in the polished version. Nothing is saved; the swap is a
 * normal undoable edit (⌘Z) and the Undo link restores the exact original.
 */
Alpine.data('aiEnhance', ({ input, kind, url, titleField = null, typeField = null }) => ({
    busy: false,
    done: false,
    empty: true,
    error: '',
    previous: null,
    stopListening: null,

    editor() {
        return document.querySelector(`trix-editor[input="${input}"]`);
    },

    init() {
        const sync = (e) => {
            if (e && e.target.getAttribute('input') !== input) return;
            const el = this.editor();
            this.empty = !el?.editor || el.editor.getDocument().toString().trim() === '';
        };
        const listeners = new AbortController();
        document.addEventListener('trix-initialize', sync, { signal: listeners.signal });
        document.addEventListener('trix-change', sync, { signal: listeners.signal });
        this.stopListening = () => listeners.abort();
        this.$nextTick(() => sync());
    },

    destroy() {
        this.stopListening?.();
    },

    field(id) {
        const el = id ? document.getElementById(id) : null;
        if (!el) return null;
        return el.tagName === 'SELECT' ? el.selectedOptions[0]?.textContent.trim() ?? null : el.value || null;
    },

    async run() {
        const el = this.editor();
        if (!el?.editor || this.busy || this.empty) return;

        const body = document.getElementById(input)?.value ?? '';
        this.busy = true;
        this.error = '';
        el.setAttribute('contenteditable', 'false');
        el.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ kind, body, title: this.field(titleField), type: this.field(typeField) }),
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                this.error = response.status === 429
                    ? 'Too many enhance requests — wait a minute and try again.'
                    : data.message ?? 'Couldn’t enhance right now. Your text is unchanged.';
                return;
            }

            el.setAttribute('contenteditable', 'true');
            this.previous = body;
            el.editor.recordUndoEntry('Enhance with Claude');
            el.editor.setSelectedRange([0, el.editor.getDocument().getLength()]);
            el.editor.insertHTML(data.html);
            this.done = true;
        } catch (e) {
            this.error = 'Couldn’t reach the server. Your text is unchanged.';
        } finally {
            this.busy = false;
            el.setAttribute('contenteditable', 'true');
            el.removeAttribute('aria-busy');
        }
    },

    undo() {
        const el = this.editor();
        if (!el?.editor || this.previous === null) return;
        el.editor.loadHTML(this.previous);
        this.previous = null;
        this.done = false;
    },
}));

Alpine.start();
