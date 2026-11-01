import Alpine from 'alpinejs';

/*
 * <x-poll-region>: while a queued job runs, re-fetch the current page in the
 * background and swap in only this region (and any extra elements named in
 * `also`), instead of reloading the whole page. Polling stops once the fresh
 * region reports data-running="false". Form fields outside the region are
 * never touched, so typing elsewhere on the page is safe.
 *
 * A child can ask for an immediate refresh with $dispatch('poll-region-now').
 * Soft navigation tears the component down with the old <body>, so destroy()
 * clears the timer.
 */
Alpine.data('pollRegion', ({ every = 4000, maxEvery = 30000, also = [], doneMessage = null } = {}) => ({
    timer: null,
    delay: every,
    stopped: false,
    busy: false,

    init() {
        this.schedule();
    },

    destroy() {
        this.stopped = true;
        clearTimeout(this.timer);
    },

    get running() {
        return this.$root.dataset.running === 'true';
    },

    schedule() {
        clearTimeout(this.timer);
        if (this.stopped || !this.running) return;
        this.timer = setTimeout(() => this.refresh(), this.delay);
    },

    async refresh() {
        if (this.busy || this.stopped) return;
        this.busy = true;
        const wasRunning = this.running;

        try {
            const response = await fetch(window.location.href, {
                headers: { Accept: 'text/html', 'X-Poll-Region': this.$root.id },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const fresh = doc.getElementById(this.$root.id);
            if (fresh) {
                this.$root.dataset.running = fresh.dataset.running ?? 'false';
                this.$root.innerHTML = fresh.innerHTML;
            }
            also.forEach((id) => {
                const current = document.getElementById(id);
                const next = doc.getElementById(id);
                if (current && next) current.innerHTML = next.innerHTML;
            });

            this.delay = every;
            if (wasRunning && !this.running && doneMessage) {
                window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: doneMessage } }));
            }
        } catch (e) {
            // Network blip or session hiccup: back off and try again.
            this.delay = Math.min(this.delay * 2, maxEvery);
        } finally {
            this.busy = false;
            this.schedule();
        }
    },
}));
