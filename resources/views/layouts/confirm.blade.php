<div x-data="confirmer()" x-init="boot()" @keydown.escape.window="open && cancel()">
    <div x-show="open" x-cloak class="fixed inset-0 z-[60] overflow-y-auto" role="dialog" aria-modal="true">
        {{-- Backdrop --}}
        <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             @click="cancel()" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm"></div>

        {{-- Dialog --}}
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                 class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-pop">
                <div class="flex gap-4 p-5 sm:p-6">
                    <span class="flex h-11 w-11 flex-none items-center justify-center rounded-full" :class="iconWrap">
                        <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor" x-html="icon"></svg>
                    </span>
                    <div class="mt-0.5 flex-1">
                        <h3 class="text-base font-semibold text-slate-900" x-text="title"></h3>
                        <p class="mt-1 text-sm leading-relaxed text-slate-500" x-text="message"></p>
                    </div>
                </div>
                <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50 px-5 py-3.5 sm:flex-row sm:justify-end sm:px-6">
                    <button type="button" @click="cancel()" class="btn-secondary">Cancel</button>
                    <button type="button" x-ref="confirmBtn" @click="confirm()" :class="confirmClass" x-text="verb"></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.confirmer = function () {
        const DANGER_ICON = '<path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.515 2.625H3.72c-1.344 0-2.187-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>';
        const QUESTION_ICON = '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1.5 1.5 0 00-1.34.83.75.75 0 11-1.32-.7A3 3 0 0113 8.5c0 1.06-.68 1.77-1.29 2.2-.24.17-.4.3-.5.42-.09.11-.09.16-.09.19a.75.75 0 01-1.5 0c0-.54.28-.95.58-1.24.28-.28.63-.5.87-.66.5-.35.62-.6.62-.91A1.5 1.5 0 0010 7zm0 7a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>';

        return {
            open: false,
            title: 'Are you sure?',
            message: '',
            verb: 'Confirm',
            tone: 'danger',
            onConfirm: null,
            get icon() { return this.tone === 'danger' ? DANGER_ICON : QUESTION_ICON; },
            get iconWrap() { return this.tone === 'danger' ? 'bg-rose-100 text-rose-600' : 'bg-brand-100 text-brand-600'; },
            get confirmClass() {
                return this.tone === 'danger'
                    ? 'btn bg-rose-600 text-white shadow-sm hover:bg-rose-700'
                    : 'btn-primary';
            },
            boot() {
                // Intercept any form marked with data-confirm before it submits.
                document.addEventListener('submit', (e) => {
                    const form = e.target;
                    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;
                    e.preventDefault();
                    this.request(form.dataset, () => HTMLFormElement.prototype.submit.call(form));
                }, true);
            },
            request(data, onConfirm) {
                this.message = data.confirm || 'This action cannot be undone.';
                this.title = data.confirmTitle || 'Are you sure?';
                this.verb = data.confirmVerb || 'Confirm';
                this.tone = data.confirmTone || 'danger';
                this.onConfirm = onConfirm;
                this.open = true;
                this.$nextTick(() => this.$refs.confirmBtn && this.$refs.confirmBtn.focus());
            },
            confirm() {
                this.open = false;
                const cb = this.onConfirm;
                this.onConfirm = null;
                if (cb) cb();
            },
            cancel() {
                this.open = false;
                this.onConfirm = null;
            },
        };
    };
</script>
