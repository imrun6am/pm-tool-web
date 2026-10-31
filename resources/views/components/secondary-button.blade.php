<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-1.5 h-10 px-4 bg-white border border-slate-300 rounded-lg font-medium text-sm text-slate-700 shadow-sm hover:bg-slate-50 active:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60 focus-visible:ring-offset-1 disabled:opacity-60 disabled:cursor-not-allowed transition-colors duration-150']) }}>
    {{ $slot }}
</button>
