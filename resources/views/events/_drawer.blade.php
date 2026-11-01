@php
    // Create (calendar) or edit (event page) — same fields, same drawer.
    $editing = $event->exists;
    // Reopen after a failed submit (validation redirects back here with the
    // `_drawer` marker in old input) or when a deep link asked for it.
    $startOpen = ($errors->any() && old('_drawer') === 'event') || $autoOpen;
@endphp
{{-- Event slide-over. Anything can open it with
     $dispatch('open-event-drawer', { date: 'YYYY-MM-DD' }) — the date (create
     only) presets the start to 09:00 that day. --}}
<div x-data="{
        open: @js($startOpen),
        show(detail) {
            const date = detail?.date;
            if (date) {
                const start = this.$refs.form.elements.starts_at;
                const end = this.$refs.form.elements.ends_at;
                start.value = date + 'T09:00';
                if (end.value && end.value < start.value) end.value = '';
            }
            this.open = true;
            this.$nextTick(() => this.$refs.form.elements.title.focus({ preventScroll: true }));
        },
     }"
     x-init="if (open) {
        $nextTick(() => ($refs.form.querySelector('[aria-invalid=true]') ?? $refs.form.elements.title).focus());
        // Drop the deep-link flag so a reload doesn't reopen the drawer.
        const url = new URL(location.href);
        url.searchParams.delete('new');
        url.searchParams.delete('edit');
        history.replaceState(history.state, '', url);
     }"
     @open-event-drawer.window="show($event.detail)"
     @keydown.escape.window="open = false">

    {{-- Rendered at <body>: the page content is its own stacking context
         (view-transition-name), so a z-index inside it can't rise above the
         app header. Teleported content keeps this component's scope and refs. --}}
    <template x-teleport="body">
    <div class="relative z-[55]">
    {{-- Backdrop --}}
    <div x-show="open" x-transition.opacity style="display: none;"
         class="fixed inset-0 bg-slate-900/40 backdrop-blur-[2px]" @click="open = false" aria-hidden="true"></div>

    {{-- Panel --}}
    <aside x-show="open" style="display: none;"
           x-transition:enter="transition duration-200 ease-out motion-reduce:transition-none" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition duration-150 ease-in motion-reduce:transition-none" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
           class="fixed inset-y-0 right-0 flex w-full max-w-lg flex-col border-l border-slate-200 bg-white shadow-2xl"
           role="dialog" aria-modal="true" aria-labelledby="event-drawer-title">

        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
            <div class="min-w-0">
                <h2 id="event-drawer-title" class="flex items-center gap-2 text-base font-semibold text-slate-900">
                    <x-icon name="calendar" variant="outline" class="h-5 w-5 flex-none text-rose-600" />
                    {{ $editing ? 'Edit event' : 'New event' }}
                </h2>
                <p class="mt-0.5 truncate text-xs text-slate-500">
                    {{ $editing ? $event->title : 'Put a meeting, deadline or milestone on the team calendar.' }}
                </p>
            </div>
            <button type="button" @click="open = false" class="btn-ghost btn-sm btn-icon -mr-1.5" aria-label="Close">
                <x-icon name="x-mark" variant="outline" class="h-5 w-5" />
            </button>
        </div>

        <form x-ref="form" method="POST" action="{{ $editing ? route('events.update', $event) : route('events.store') }}"
              class="flex min-h-0 flex-1 flex-col">
            @csrf
            @if ($editing) @method('PUT') @endif
            <input type="hidden" name="_drawer" value="event">

            <div class="scroll-area flex-1 overflow-y-auto px-5 py-6 sm:px-6">
                @include('events.form')
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50/70 px-5 py-3.5 sm:px-6">
                <button type="button" @click="open = false" class="btn-ghost">Cancel</button>
                <button type="submit" class="btn-primary">{{ $editing ? 'Save changes' : 'Create event' }}</button>
            </div>
        </form>
    </aside>
    </div>
    </template>
</div>
