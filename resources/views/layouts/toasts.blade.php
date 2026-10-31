@php
    $initialToasts = collect([
        ['type' => 'success', 'message' => session('success')],
        ['type' => 'error', 'message' => session('error')],
        ['type' => 'warning', 'message' => session('overlap_warning')],
    ])->filter(fn ($t) => filled($t['message']))->values();
@endphp

<div x-data="toaster(@js($initialToasts))" x-init="boot()"
     class="pointer-events-none fixed inset-x-0 top-3 z-[70] flex flex-col items-center gap-2.5 px-3 sm:inset-x-auto sm:right-5 sm:top-5 sm:items-end sm:px-0">
    <template x-for="t in toasts" :key="t.id">
        <div x-show="t.show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-2 sm:translate-x-6 sm:translate-y-0"
             x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 sm:translate-x-6"
             @mouseenter="pause(t)" @mouseleave="resume(t)"
             class="pointer-events-auto flex w-full max-w-sm items-start gap-3 overflow-hidden rounded-xl border border-slate-200 bg-white p-3.5 shadow-pop"
             role="status" aria-live="polite">
            <span class="mt-0.5 flex h-6 w-6 flex-none items-center justify-center rounded-full" :class="t.iconClass">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" x-html="t.icon"></svg>
            </span>
            <p class="flex-1 whitespace-pre-line text-sm leading-snug text-slate-700" x-text="t.message"></p>
            <button type="button" @click="dismiss(t.id)" class="flex-none rounded p-0.5 text-slate-300 transition hover:text-slate-600" aria-label="Dismiss">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
            </button>
        </div>
    </template>
</div>

<script>
    window.toaster = function (initial) {
        const ICONS = {
            success: '<path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0l-3.5-3.5a1 1 0 111.4-1.4l2.8 2.79 6.8-6.79a1 1 0 011.4 0z" clip-rule="evenodd"/>',
            error: '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.7 7.3a1 1 0 00-1.4 1.4L8.6 10l-1.3 1.3a1 1 0 101.4 1.4L10 11.4l1.3 1.3a1 1 0 001.4-1.4L11.4 10l1.3-1.3a1 1 0 00-1.4-1.4L10 8.6 8.7 7.3z" clip-rule="evenodd"/>',
            warning: '<path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.515 2.625H3.72c-1.344 0-2.187-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>',
            info: '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>',
        };
        const ICON_CLASS = {
            success: 'bg-emerald-50 text-emerald-600',
            error: 'bg-rose-50 text-rose-600',
            warning: 'bg-amber-50 text-amber-600',
            info: 'bg-brand-50 text-brand-600',
        };
        const DURATION = { success: 4500, info: 5000, warning: 8000, error: 7000 };

        return {
            toasts: [],
            boot() {
                (initial || []).forEach((t) => this.push(t.type, t.message));
                window.addEventListener('toast', (e) => this.push(e.detail.type || 'info', e.detail.message));
            },
            push(type, message) {
                if (!message) return;
                const t = {
                    id: Date.now() + Math.random(),
                    type, message, show: true,
                    icon: ICONS[type] || ICONS.info,
                    iconClass: ICON_CLASS[type] || ICON_CLASS.info,
                    duration: DURATION[type] || 5000,
                    remaining: DURATION[type] || 5000,
                    timer: null, startedAt: 0,
                };
                this.toasts.push(t);
                this.start(t);
            },
            start(t) {
                t.startedAt = Date.now();
                t.timer = setTimeout(() => this.dismiss(t.id), t.remaining);
            },
            pause(t) {
                clearTimeout(t.timer);
                t.remaining -= Date.now() - t.startedAt;
            },
            resume(t) {
                if (t.remaining > 0) this.start(t);
            },
            dismiss(id) {
                const t = this.toasts.find((t) => t.id === id);
                if (!t) return;
                clearTimeout(t.timer);
                t.show = false;
                setTimeout(() => { this.toasts = this.toasts.filter((x) => x.id !== id); }, 220);
            },
        };
    };
</script>
