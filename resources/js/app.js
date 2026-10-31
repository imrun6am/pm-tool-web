import Alpine from 'alpinejs';
import 'trix';

window.Alpine = Alpine;

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

    init() {
        this.$watch('mobileOpen', (open) => {
            document.documentElement.classList.toggle('overflow-hidden', open);
            this.$nextTick(() => (open ? this.$refs.sidebarClose : this.$refs.menuButton)?.focus());
        });
        desktop.addEventListener('change', (e) => {
            if (e.matches) this.mobileOpen = false;
        });
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
 * server-rendered so icons come from <x-icon>.
 */
Alpine.data('commandPalette', (entries = []) => ({
    entries,
    open: false,
    query: '',
    active: 0,
    returnFocus: null,

    get results() {
        const q = this.query.trim().toLowerCase();
        return this.entries
            .map((entry, i) => i)
            .filter((i) => q === '' || `${this.entries[i].label} ${this.entries[i].group}`.toLowerCase().includes(q));
    },

    init() {
        this.$watch('query', () => (this.active = 0));
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
        this.open = true;
        this.$nextTick(() => this.$refs.input.focus());
    },

    close() {
        this.open = false;
        this.returnFocus?.focus?.();
    },

    move(step) {
        const count = this.results.length;
        if (!count) return;
        this.active = (this.active + step + count) % count;
        this.$nextTick(() => document.getElementById(`cmdk-opt-${this.results[this.active]}`)?.scrollIntoView({ block: 'nearest' }));
    },

    go(position = this.active) {
        const entry = this.entries[this.results[position]];
        if (entry) window.location.assign(entry.url);
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
        window.addEventListener('scroll', () => {
            const atBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4;
            if (atBottom && ids.length) this.active = ids[ids.length - 1];
        }, { passive: true });
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

    editor() {
        return document.querySelector(`trix-editor[input="${input}"]`);
    },

    init() {
        const sync = (e) => {
            if (e && e.target.getAttribute('input') !== input) return;
            const el = this.editor();
            this.empty = !el?.editor || el.editor.getDocument().toString().trim() === '';
        };
        document.addEventListener('trix-initialize', sync);
        document.addEventListener('trix-change', sync);
        this.$nextTick(() => sync());
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
