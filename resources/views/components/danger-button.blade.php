<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-1.5 h-10 px-4 bg-rose-600 border border-transparent rounded-lg font-medium text-sm text-white shadow-sm hover:bg-rose-500 active:bg-rose-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/60 focus-visible:ring-offset-1 disabled:opacity-60 disabled:cursor-not-allowed transition-colors duration-150']) }}>
    {{ $slot }}
</button>
