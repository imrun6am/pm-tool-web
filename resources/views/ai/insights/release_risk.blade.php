@php
    /** @var \App\Models\AiInsight $insight */
    $p = $insight->payload ?? [];
    $status = $p['status'] ?? 'at_risk';
    $tone = ['on_track' => 'badge-success', 'at_risk' => 'badge-warning', 'off_track' => 'badge-danger'][$status] ?? 'badge-slate';
    $dot = ['high' => 'bg-danger-500', 'medium' => 'bg-warning-500', 'low' => 'bg-slate-400'];
    $s = $p['signals'] ?? [];
@endphp
<div class="space-y-4" x-data="{ copied: false }">
    <div class="flex flex-wrap items-center gap-2">
        <span class="badge {{ $tone }}">{{ \App\Services\Ai\Insights\ReleaseRiskGenerator::STATUSES[$status] ?? $status }}</span>
        <p class="text-sm font-medium text-slate-900">{{ $p['headline'] ?? '' }}</p>
    </div>

    @if ($s)
        <dl class="grid grid-cols-3 gap-2 text-center">
            <div class="rounded-lg bg-slate-50 px-2 py-2">
                <dt class="text-[11px] text-slate-500">Done</dt>
                <dd class="text-sm font-semibold text-slate-900">{{ $s['done_pct'] !== null ? $s['done_pct'].'%' : '—' }}</dd>
            </div>
            <div class="rounded-lg bg-slate-50 px-2 py-2">
                <dt class="text-[11px] text-slate-500">Time used</dt>
                <dd class="text-sm font-semibold text-slate-900">{{ $s['time_pct'] }}%</dd>
            </div>
            <div @class(['rounded-lg px-2 py-2', 'bg-danger-50' => ($s['overdue'] ?? 0) > 0, 'bg-slate-50' => ($s['overdue'] ?? 0) === 0])>
                <dt class="text-[11px] text-slate-500">Overdue</dt>
                <dd class="text-sm font-semibold text-slate-900">{{ $s['overdue'] }}</dd>
            </div>
        </dl>
    @endif

    @if (! empty($p['reasons']))
        <div>
            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Why</h4>
            <ul class="mt-1.5 space-y-1.5">
                @foreach ($p['reasons'] as $r)
                    <li class="flex gap-2 text-sm text-slate-700">
                        <span class="mt-1.5 h-2 w-2 flex-none rounded-full {{ $dot[$r['severity'] ?? 'low'] ?? $dot['low'] }}"
                              title="{{ ucfirst($r['severity'] ?? 'low') }} severity"></span>
                        <span>{{ $r['text'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! empty($p['recommendations']))
        <div>
            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Suggested actions</h4>
            <ul class="mt-1.5 list-inside list-disc space-y-1 text-sm text-slate-700 marker:text-violet-400">
                @foreach ($p['recommendations'] as $rec)
                    <li>{{ $rec }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (filled($p['stakeholder_update'] ?? null))
        <div class="rounded-lg bg-violet-50/60 p-3 ring-1 ring-inset ring-violet-600/10">
            <div class="flex items-center justify-between gap-2">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-violet-800">Stakeholder update</h4>
                <button type="button" class="text-xs font-medium text-violet-700 hover:underline"
                        @click="navigator.clipboard?.writeText($refs.update.innerText).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
                        x-text="copied ? 'Copied' : 'Copy'">Copy</button>
            </div>
            <p x-ref="update" class="mt-1 text-sm leading-6 text-slate-700">{{ $p['stakeholder_update'] }}</p>
        </div>
    @endif
</div>
