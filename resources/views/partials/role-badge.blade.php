@php
    $role = $role ?? 'viewer';
    $variant = $variant ?? 'pill'; // pill | text | dot
    $label = \App\Models\User::ROLES[$role] ?? ucfirst($role);
    // Literal classes so Tailwind keeps them.
    $pill = match ($role) {
        'admin' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/15',
        'cto' => 'bg-purple-50 text-purple-700 ring-purple-600/15',
        'tech_lead' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'team_lead' => 'bg-cyan-50 text-cyan-800 ring-cyan-600/20',
        'developer' => 'bg-blue-50 text-blue-700 ring-blue-600/15',
        'qa' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        default => 'bg-slate-100 text-slate-700 ring-slate-500/10',
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
    <span class="badge ring-1 ring-inset {{ $pill }}">{{ $label }}</span>
@endif
