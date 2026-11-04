{{-- A "My tasks" style filter button. Pass $url, $mine and $label every time: an @include also
     sees the parent's variables, so a default would pick up a loop's leftover $label. --}}
<a href="{{ $url }}" role="button" aria-pressed="{{ $mine ? 'true' : 'false' }}" @keydown.space.prevent="$el.click()"
   @class([
       'inline-flex h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-medium ring-1 ring-inset transition-colors',
       'bg-brand-600 text-white ring-brand-600 hover:bg-brand-700' => $mine,
       'bg-white text-slate-700 ring-slate-300 hover:bg-slate-50' => ! $mine,
   ])>
    <x-avatar :user="auth()->user()" size="xs" @class(['ring-1 ring-white/60' => $mine]) />
    {{ $label }}
</a>
