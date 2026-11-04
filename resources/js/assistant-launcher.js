import Alpine from 'alpinejs';

/*
 * The assistant's button: all of it that ships with the page. The panel's script,
 * styles and HTML load on first open (or first hover), so someone who never opens
 * it pays for one button. The root is data-turbo-permanent: Turbo moves it into
 * each new <body> intact, and Alpine treats that as a move, so state and a reply
 * in progress carry across page changes. sessionStorage carries the open state
 * across full page loads.
 */
const OPEN_KEY = 'rp.assistant.open';

const storage = (fn) => {
    try {
        return fn(sessionStorage);
    } catch (e) {
        return null; // Storage blocked: it just won't reopen after a full reload.
    }
};

const isTyping = (el) => el instanceof Element && !!el.closest('input, textarea, select, [contenteditable]:not([contenteditable="false"]), trix-editor');
const dialogOpen = () => [...document.querySelectorAll('[aria-modal="true"]')].some((el) => el.getClientRects().length > 0);

Alpine.data('assistantLauncher', ({ panelUrl }) => ({
    panelOpen: false,
    panelReady: false,
    panelFailed: false,
    working: false,
    attention: false,
    loading: null,

    init() {
        if (storage((s) => s.getItem(OPEN_KEY)) === '1') this.open(false);
    },

    preload() {
        this.loading ??= this.loadPanel();

        return this.loading;
    },

    async loadPanel() {
        try {
            const [, response] = await Promise.all([
                import('./assistant'),
                fetch(panelUrl, { headers: { Accept: 'application/json' } }),
            ]);
            if (!response.ok) throw new Error(`Panel answered ${response.status}`);
            const html = await response.text();
            // Alpine's observer starts the component when it lands.
            this.$refs.host.innerHTML = html;
            this.panelReady = true;
            this.panelFailed = false;
        } catch (e) {
            this.loading = null;
            this.panelFailed = true;
        }
    },

    open(focus = true) {
        this.panelOpen = true;
        this.attention = false;
        storage((s) => s.setItem(OPEN_KEY, '1'));
        this.preload().then(() => {
            if (focus) window.dispatchEvent(new CustomEvent('assistant-focus'));
        });
    },

    close() {
        if (!this.panelOpen) return;
        this.panelOpen = false;
        storage((s) => s.removeItem(OPEN_KEY));
        this.$refs.launcher.focus({ preventScroll: true });
    },

    toggle() {
        this.panelOpen ? this.close() : this.open();
    },

    retry() {
        this.panelFailed = false;
        this.preload();
    },

    onKeydown(e) {
        // Esc from the panel, or from nowhere in particular (a confirmed draft's button goes away).
        if (e.key === 'Escape') {
            const focus = document.activeElement;
            if (!dialogOpen() && (!focus || focus === document.body || this.$root.contains(focus))) this.close();
            return;
        }
        if (e.key.toLowerCase() !== 'a' || e.metaKey || e.ctrlKey || e.altKey || e.shiftKey || e.defaultPrevented) return;
        if (isTyping(e.target) || dialogOpen() || this.panelOpen) return;
        e.preventDefault();
        this.open();
    },
}));
