@php
    $status = $status ?? 'todo';
    $label = \App\Models\Task::STATUSES[$status] ?? ucfirst($status);
    // Full literal classes so Tailwind's scanner keeps them.
    $classes = match ($status) {
        'todo' => 'badge-slate',
        'in_progress' => 'badge-info',
        'in_review' => 'badge-warning',
        'recheck' => 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-600/20',
        'done' => 'badge-success',
        'archive' => 'badge-slate opacity-80',
        default => 'badge-slate',
    };
@endphp
<span class="badge {{ $classes }}"><span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>{{ $label }}</span>
