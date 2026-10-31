{{-- Expects: $activities (Collection or paginator) --}}
@php
    $dot = fn ($event) => match ($event) {
        'created' => 'bg-emerald-500',
        'deleted' => 'bg-rose-500',
        default => 'bg-indigo-500',
    };
@endphp

@forelse ($activities as $activity)
    <div class="flex gap-3 px-1 py-3">
        <div class="mt-1.5 flex-none">
            <span class="block h-2.5 w-2.5 rounded-full {{ $dot($activity->event) }}"></span>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-sm text-slate-800">
                <span class="font-medium">{{ $activity->causer->name ?? 'System' }}</span>
                {{ \Illuminate\Support\Str::of($activity->description)->lower() }}
            </p>
            <p class="text-xs text-slate-400">{{ $activity->created_at->diffForHumans() }} · {{ $activity->created_at->format('M j, Y g:i a') }}</p>

            @if ($activity->event === 'updated' && count($activity->changes()))
                <div class="mt-1.5 space-y-0.5 rounded-md bg-slate-50 p-2 text-xs text-slate-600">
                    @foreach ($activity->changes() as $field => $vals)
                        <div class="flex flex-wrap items-center gap-1">
                            <span class="font-medium text-slate-500">{{ \Illuminate\Support\Str::of($field)->replace('_', ' ')->title() }}:</span>
                            <span class="rounded bg-rose-50 px-1 text-rose-700 line-through">{{ \Illuminate\Support\Str::limit((string) ($vals['old'] ?? '—'), 60) ?: '—' }}</span>
                            <span class="text-slate-300">→</span>
                            <span class="rounded bg-emerald-50 px-1 text-emerald-700">{{ \Illuminate\Support\Str::limit((string) ($vals['new'] ?? '—'), 60) ?: '—' }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@empty
    <p class="py-6 text-center text-sm text-slate-400">No activity recorded yet.</p>
@endforelse
