import Alpine from 'alpinejs';

/*
 * The task side panel. Any link with data-task-panel opens its task in the
 * drawer; a modified click (open in a new tab) still follows the link. Forms in
 * the panel are marked so their answer comes back here (KeepTaskPanelOpen), and
 * after a change the page behind refreshes when the panel closes.
 */
Alpine.data('taskPanel', () => ({
    open: false,
    taskId: null,
    changed: false,
    opener: null,

    bound: false,

    // On first open, not at init: the drawer is teleported, and Alpine caches
    // $refs the first time it's read, so reading it earlier would miss the frame.
    bind() {
        if (this.bound) return;
        this.bound = true;

        this.$refs.frame.addEventListener('formdata', (e) => {
            if (this.taskId) e.formData.set('_panel', this.taskId);
        });
        this.$refs.frame.addEventListener('turbo:submit-end', (e) => {
            if (e.detail.success) this.changed = true;
        });
    },

    maybeOpen(e) {
        const link = e.target.closest?.('a[data-task-panel]');
        if (!link || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

        e.preventDefault();
        this.bind();
        this.opener = link;
        this.taskId = link.dataset.taskId;
        this.changed = false;
        this.$refs.frame.src = link.dataset.taskPanel;
        this.open = true;
        this.$nextTick(() => this.$refs.close.focus({ preventScroll: true }));
    },

    close() {
        if (!this.open) return;

        this.open = false;
        this.taskId = null;
        this.$refs.frame.removeAttribute('src');
        this.$refs.frame.replaceChildren();

        if (this.changed) {
            window.Turbo?.visit(window.location.href, { action: 'replace' });
        } else if (this.opener?.isConnected) {
            this.opener.focus({ preventScroll: true });
        }
    },
}));
