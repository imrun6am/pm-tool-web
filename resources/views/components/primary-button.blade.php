<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-1.5 h-10 px-4 bg-brand-600 border border-transparent rounded-lg font-medium text-sm text-white shadow-sm hover:bg-brand-700 active:bg-brand-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60 focus-visible:ring-offset-1 disabled:opacity-60 disabled:cursor-not-allowed transition-colors duration-150']) }}>
    {{ $slot }}
</button>
