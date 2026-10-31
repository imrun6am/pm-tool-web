@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'h-10 px-3 text-sm text-slate-800 border-slate-300 focus:border-brand-500 focus:ring-brand-500 rounded-lg shadow-sm transition']) }}>
