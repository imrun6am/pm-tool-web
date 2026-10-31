@php
    $status = $status ?? 'todo';
    $label = \App\Models\Task::STATUSES[$status] ?? ucfirst($status);
    // Full literal classes so Tailwind's scanner keeps them.
    $classes = match ($status) {
        'todo' => 'bg-slate-100 text-slate-700',
        'in_progress' => 'bg-blue-100 text-blue-700',
        'in_review' => 'bg-amber-100 text-amber-800',
        'recheck' => 'bg-orange-100 text-orange-700',
        'done' => 'bg-emerald-100 text-emerald-700',
        'archive' => 'bg-slate-200 text-slate-600',
        default => 'bg-slate-100 text-slate-700',
    };
@endphp
<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $classes }}">{{ $label }}</span>
