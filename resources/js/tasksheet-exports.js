import Alpine from 'alpinejs';

/*
 * Tasksheet exports list: polls the status endpoint while any export is still
 * queued or generating, and stops once none are. Soft navigation tears the
 * component down with the old <body>, so destroy() must clear the timer.
 */
const RUNNING = ['pending', 'processing'];
const POLL_MS = 2000;
const MAX_BACKOFF_MS = 30000;

Alpine.data('tasksheetExports', (initial = {}, statusUrl = '', tones = {}) => ({
    rows: initial,
    timer: null,
    delay: POLL_MS,
    stopped: false,

    init() {
        this.schedule();
    },

    destroy() {
        this.stopped = true;
        clearTimeout(this.timer);
    },

    row(id) {
        return this.rows[id] || {};
    },

    running(id) {
        return RUNNING.includes(this.row(id).status);
    },

    tone(id) {
        return tones[this.row(id).status] || 'badge-slate';
    },

    progressText(id) {
        const r = this.row(id);
        if (r.status === 'pending') return 'Waiting for the queue worker…';
        if (r.merging) return 'Merging pages…';
        if (!r.chunks_total) return 'Starting…';
        return `${r.chunks_done} of ${r.chunks_total} parts rendered · ${r.progress}%`;
    },

    rowsText(id) {
        const n = this.row(id).rows_total || 0;
        return `${n.toLocaleString()} ${n === 1 ? 'row' : 'rows'}`;
    },

    runningIds() {
        return Object.keys(this.rows).filter((id) => this.running(id));
    },

    schedule() {
        clearTimeout(this.timer);
        if (this.stopped || this.runningIds().length === 0) return;
        this.timer = setTimeout(() => this.poll(), this.delay);
    },

    async poll() {
        // A background tab doesn't need live progress; check again later.
        if (document.hidden) return this.schedule();

        const ids = this.runningIds();
        try {
            const res = await fetch(`${statusUrl}?ids=${ids.join(',')}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const { data } = await res.json();
            const seen = new Set(data.map((d) => String(d.id)));
            data.forEach((fresh) => this.update(fresh));
            // Deleted elsewhere (another tab): stop asking about it.
            ids.filter((id) => !seen.has(id)).forEach((id) => {
                this.rows[id] = { ...this.row(id), status: 'gone', status_label: 'Deleted' };
            });
            this.delay = POLL_MS;
        } catch {
            // Network blip or a deploy: back off rather than hammering.
            this.delay = Math.min(this.delay * 2, MAX_BACKOFF_MS);
        }

        this.schedule();
    },

    update(fresh) {
        const previous = this.row(fresh.id).status;
        this.rows[fresh.id] = { ...this.row(fresh.id), ...fresh };

        if (previous === fresh.status || this.stopped) return;
        if (fresh.status === 'ready') this.toast('success', 'Your tasksheet PDF is ready to download.');
        if (fresh.status === 'failed') this.toast('error', 'A tasksheet export failed. You can retry it from the list.');
    },

    toast(type, message) {
        window.dispatchEvent(new CustomEvent('toast', { detail: { type, message } }));
    },
}));
