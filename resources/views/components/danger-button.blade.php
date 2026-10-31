<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn inline-flex border border-danger-700/30 bg-danger-600 text-white shadow-btn hover:bg-danger-700 active:bg-danger-800 focus-visible:ring-danger-500/60']) }}>
    {{ $slot }}
</button>
