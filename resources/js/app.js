import Alpine from 'alpinejs';
import 'trix';
import 'trix/dist/trix.css';

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

Alpine.start();
