@php
    /** @var \App\Models\AiInsight $insight */
    $p = $insight->payload ?? [];
    $names = fn (array $list) => implode(', ', $list);
@endphp
<div class="space-y-4">
    <p class="text-sm leading-6 text-slate-700">{{ $p['summary'] ?? '' }}</p>

    <dl class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        <div class="rounded-lg bg-slate-50 px-3 py-2">
            <dt class="text-[11px] text-slate-500">Filled in</dt>
            <dd class="text-sm font-semibold text-slate-900">{{ $p['filled'] ?? 0 }} / {{ $p['members'] ?? 0 }}</dd>
        </div>
        <div @class(['rounded-lg px-3 py-2', 'bg-warning-50' => ! empty($p['missed_standup']), 'bg-slate-50' => empty($p['missed_standup'])])>
            <dt class="text-[11px] text-slate-500">Missed standup</dt>
            <dd class="truncate text-sm font-semibold text-slate-900" title="{{ $names($p['missed_standup'] ?? []) }}">{{ count($p['missed_standup'] ?? []) }}</dd>
        </div>
        <div class="rounded-lg bg-slate-50 px-3 py-2">
            <dt class="text-[11px] text-slate-500">On leave</dt>
            <dd class="truncate text-sm font-semibold text-slate-900" title="{{ $names($p['on_leave'] ?? []) }}">{{ count($p['on_leave'] ?? []) }}</dd>
        </div>
        <div @class(['rounded-lg px-3 py-2', 'bg-danger-50' => ! empty($p['blockers']), 'bg-slate-50' => empty($p['blockers'])])>
            <dt class="text-[11px] text-slate-500">Blockers</dt>
            <dd class="text-sm font-semibold text-slate-900">{{ count($p['blockers'] ?? []) }}</dd>
        </div>
    </dl>

    <div class="grid gap-4 md:grid-cols-2">
        @foreach (['highlights' => ['Highlights', 'text-emerald-600', 'check'], 'blockers' => ['Blockers', 'text-danger-600', 'exclamation-circle']] as $key => [$title, $color, $icon])
            @if (! empty($p[$key]))
                <div>
                    <h4 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <x-icon :name="$icon" variant="outline" class="h-4 w-4 {{ $color }}" /> {{ $title }}
                    </h4>
                    <ul class="mt-1.5 space-y-1.5 text-sm text-slate-700">
                        @foreach ($p[$key] as $item)
                            <li><span class="font-medium text-slate-900">{{ $item['member'] }}</span> — {{ $item['text'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach
    </div>

    @if (! empty($p['follow_ups']))
        <div class="rounded-lg bg-violet-50/60 p-3 ring-1 ring-inset ring-violet-600/10">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-violet-800">For you today</h4>
            <ul class="mt-1 list-inside list-disc space-y-1 text-sm text-slate-700 marker:text-violet-400">
                @foreach ($p['follow_ups'] as $f)
                    <li>{{ $f }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! empty($p['not_filled']))
        <p class="text-xs text-slate-500">Not filled in: {{ $names($p['not_filled']) }}</p>
    @endif
</div>
