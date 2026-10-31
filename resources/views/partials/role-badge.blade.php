@php
    $role = $role ?? 'viewer';
    $variant = $variant ?? 'pill'; // pill | text | dot
    $label = \App\Models\User::ROLES[$role] ?? ucfirst($role);
    // Literal classes so Tailwind keeps them.
    $pill = match ($role) {
        'admin' => 'bg-indigo-100 text-indigo-700',
        'cto' => 'bg-purple-100 text-purple-700',
        'tech_lead' => 'bg-emerald-100 text-emerald-700',
        'team_lead' => 'bg-cyan-100 text-cyan-700',
        'developer' => 'bg-blue-100 text-blue-700',
        'qa' => 'bg-amber-100 text-amber-800',
        default => 'bg-slate-100 text-slate-600',
    };
    $text = match ($role) {
        'admin' => 'text-indigo-600',
        'cto' => 'text-purple-600',
        'tech_lead' => 'text-emerald-700',
        'team_lead' => 'text-cyan-700',
        'developer' => 'text-blue-600',
        'qa' => 'text-amber-700',
        default => 'text-slate-500',
    };
    $dot = match ($role) {
        'admin' => 'bg-indigo-500',
        'cto' => 'bg-purple-500',
        'tech_lead' => 'bg-emerald-500',
        'team_lead' => 'bg-cyan-500',
        'developer' => 'bg-blue-500',
        'qa' => 'bg-amber-500',
        default => 'bg-slate-400',
    };
@endphp
@if ($variant === 'text')
    <span class="text-[11px] font-semibold uppercase tracking-wide {{ $text }}">{{ $label }}</span>
@elseif ($variant === 'dot')
    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600">
        <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>{{ $label }}
    </span>
@else
    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $pill }}">{{ $label }}</span>
@endif
