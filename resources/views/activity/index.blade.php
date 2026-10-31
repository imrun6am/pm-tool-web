<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="eyebrow">Audit trail</p>
                <h2 class="page-title">Activity</h2>
            </div>
            @if ($filters['causer_id'] || $filters['event'])
                <a href="{{ route('activity.index') }}" class="btn-secondary btn-sm">Clear filters</a>
            @endif
        </div>
    </x-slot>

    @php
        // Shared palette so cards, charts and the feed all speak the same colour language.
        $eventMeta = [
            'created' => ['label' => 'Created', 'hex' => '#10b981', 'bg' => 'bg-emerald-500', 'soft' => 'bg-emerald-50', 'text' => 'text-emerald-600'],
            'updated' => ['label' => 'Updated', 'hex' => '#6366f1', 'bg' => 'bg-brand-500', 'soft' => 'bg-brand-50', 'text' => 'text-brand-600'],
            'deleted' => ['label' => 'Deleted', 'hex' => '#f43f5e', 'bg' => 'bg-rose-500', 'soft' => 'bg-rose-50', 'text' => 'text-rose-600'],
        ];
    @endphp

    {{-- On lg+, the content becomes a fixed-height flex column: the KPI cards and
         filters stay pinned while each of the two panes scrolls independently.
         On smaller screens it falls back to normal page scroll. --}}
    <div class="py-8 lg:h-[calc(100vh-9.5rem)] lg:overflow-hidden lg:py-6">
        <div class="app-container flex h-full flex-col gap-6">

            {{-- ============================ KPI CARDS ============================ --}}
            <div class="grid shrink-0 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Total --}}
                <div class="card card-pad">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="eyebrow">Total events</p>
                            <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900 tabular">{{ number_format($stats['total']) }}</p>
                        </div>
                        <span class="flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4.75A.75.75 0 013.75 4h12.5a.75.75 0 010 1.5H3.75A.75.75 0 013 4.75zM3 10a.75.75 0 01.75-.75h12.5a.75.75 0 010 1.5H3.75A.75.75 0 013 10zm0 5.25a.75.75 0 01.75-.75h12.5a.75.75 0 010 1.5H3.75a.75.75 0 01-.75-.75z"/></svg>
                        </span>
                    </div>
                    <p class="mt-3 text-xs text-slate-400">
                        {{ $filters['causer_id'] ? 'For selected user' : 'All recorded activity' }}
                    </p>
                </div>

                {{-- Today --}}
                <div class="card card-pad">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="eyebrow">Today</p>
                            <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900 tabular">{{ number_format($stats['today']) }}</p>
                        </div>
                        <span class="flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd"/></svg>
                        </span>
                    </div>
                    <p class="mt-3 text-xs text-slate-400">{{ number_format($stats['week']) }} in the last 7 days</p>
                </div>

                {{-- Contributors --}}
                <div class="card card-pad">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="eyebrow">Contributors</p>
                            <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900 tabular">{{ number_format($stats['contributors']) }}</p>
                        </div>
                        <span class="flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z"/></svg>
                        </span>
                    </div>
                    <p class="mt-3 text-xs text-slate-400">People with logged activity</p>
                </div>

                {{-- Event split --}}
                <div class="card card-pad">
                    <p class="eyebrow">Event breakdown</p>
                    <div class="mt-3 space-y-2">
                        @foreach ($eventMeta as $key => $meta)
                            @php $val = $stats[$key]; $pct = $stats['total'] ? round($val / $stats['total'] * 100) : 0; @endphp
                            <div class="flex items-center gap-2 text-xs">
                                <span class="h-2 w-2 flex-none rounded-full {{ $meta['bg'] }}"></span>
                                <span class="w-14 flex-none text-slate-500">{{ $meta['label'] }}</span>
                                <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                                    <span class="block h-full {{ $meta['bg'] }}" style="width: {{ $pct }}%"></span>
                                </span>
                                <span class="w-8 flex-none text-right font-medium tabular text-slate-700">{{ number_format($val) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ============================ FILTERS ============================ --}}
            <form method="GET" action="{{ route('activity.index') }}" class="card shrink-0 p-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500">User</label>
                        <select name="causer_id" class="field-input">
                            <option value="">Everyone</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected($filters['causer_id'] === $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Event</label>
                        <select name="event" class="field-input">
                            <option value="">All events</option>
                            @foreach (['created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted'] as $val => $label)
                                <option value="{{ $val }}" @selected($filters['event'] === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button class="btn-primary btn-sm">Filter</button>
                        <a href="{{ route('activity.index') }}" class="btn-secondary btn-sm">Reset</a>
                    </div>
                </div>
            </form>

            {{-- ==================== FEED (left) + CHARTS (right) ==================== --}}
            <div class="grid gap-6 lg:min-h-0 lg:flex-1 lg:grid-cols-3">

                {{-- ----------------------- Activity feed ----------------------- --}}
                <div class="lg:col-span-2 lg:flex lg:min-h-0 lg:flex-col">
                    <div class="card lg:flex lg:min-h-0 lg:flex-1 lg:flex-col">
                        <div class="card-header shrink-0">
                            <h3 class="text-sm font-semibold text-slate-900">Activity log</h3>
                            <span class="text-xs text-slate-400">{{ $activities->total() }} {{ Str::plural('entry', $activities->total()) }}</span>
                        </div>
                        <div class="px-6 py-2 lg:min-h-0 lg:flex-1 lg:overflow-y-auto">
                            @include('partials.activity-list', ['activities' => $activities])
                        </div>
                    </div>

                    @if ($activities->hasPages())
                        <div class="mt-4 shrink-0">{{ $activities->links() }}</div>
                    @endif
                </div>

                {{-- ------------------------- Charts --------------------------- --}}
                <div class="space-y-6 lg:min-h-0 lg:overflow-y-auto lg:pr-1">

                    {{-- 14-day trend bar chart --}}
                    @php
                        $maxTrend = max(1, collect($trend)->max('count'));
                        $trendTotal = collect($trend)->sum('count');
                    @endphp
                    <div class="card card-pad">
                        <div class="flex items-baseline justify-between">
                            <h3 class="text-sm font-semibold text-slate-900">Last 14 days</h3>
                            <span class="text-xs text-slate-400">{{ number_format($trendTotal) }} events</span>
                        </div>
                        <div class="mt-4 flex h-28 items-end gap-1">
                            @foreach ($trend as $day)
                                @php $h = $day['count'] ? max(6, round($day['count'] / $maxTrend * 100)) : 2; @endphp
                                <div class="group relative flex flex-1 items-end" style="height: 100%">
                                    <div class="w-full rounded-t {{ $day['count'] ? 'bg-brand-500 group-hover:bg-brand-600' : 'bg-slate-100' }} transition-colors"
                                         style="height: {{ $h }}%"></div>
                                    {{-- tooltip --}}
                                    <div class="pointer-events-none absolute -top-9 left-1/2 z-10 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[11px] font-medium text-white opacity-0 shadow-pop transition-opacity group-hover:opacity-100">
                                        {{ $day['count'] }} · {{ $day['date']->format('M j') }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-2 flex justify-between text-[11px] text-slate-400 tabular">
                            <span>{{ $trend[0]['date']->format('M j') }}</span>
                            <span>{{ $trend[count($trend) - 1]['date']->format('M j') }}</span>
                        </div>
                    </div>

                    {{-- Event donut --}}
                    <div class="card card-pad">
                        <h3 class="text-sm font-semibold text-slate-900">Events</h3>
                        @if ($stats['total'] > 0)
                            <div class="mt-4 flex items-center gap-5">
                                <div class="relative h-28 w-28 flex-none">
                                    <svg viewBox="0 0 36 36" class="h-full w-full -rotate-90">
                                        <circle cx="18" cy="18" r="15.9155" fill="none" stroke="#f1f5f9" stroke-width="3.5"/>
                                        @php $cursor = 0; @endphp
                                        @foreach ($eventMeta as $key => $meta)
                                            @php
                                                $pct = $stats['total'] ? $stats[$key] / $stats['total'] * 100 : 0;
                                            @endphp
                                            @if ($pct > 0)
                                                <circle cx="18" cy="18" r="15.9155" fill="none"
                                                        stroke="{{ $meta['hex'] }}" stroke-width="3.5"
                                                        stroke-dasharray="{{ $pct }} {{ 100 - $pct }}"
                                                        stroke-dashoffset="{{ $cursor > 0 ? 100 - $cursor : 0 }}"/>
                                                @php $cursor += $pct; @endphp
                                            @endif
                                        @endforeach
                                    </svg>
                                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                                        <span class="text-xl font-semibold tabular text-slate-900">{{ number_format($stats['total']) }}</span>
                                        <span class="text-[10px] uppercase tracking-wide text-slate-400">events</span>
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1 space-y-2">
                                    @foreach ($eventMeta as $key => $meta)
                                        @php $pct = $stats['total'] ? round($stats[$key] / $stats['total'] * 100) : 0; @endphp
                                        <div class="flex items-center gap-2 text-xs">
                                            <span class="h-2.5 w-2.5 flex-none rounded-sm" style="background-color: {{ $meta['hex'] }}"></span>
                                            <span class="flex-1 text-slate-600">{{ $meta['label'] }}</span>
                                            <span class="font-medium tabular text-slate-900">{{ number_format($stats[$key]) }}</span>
                                            <span class="w-9 text-right tabular text-slate-400">{{ $pct }}%</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <p class="py-6 text-center text-sm text-slate-400">No events to chart.</p>
                        @endif
                    </div>

                    {{-- Top contributors --}}
                    <div class="card card-pad">
                        <h3 class="text-sm font-semibold text-slate-900">Top contributors</h3>
                        @php $maxContrib = max(1, $topContributors->max('c') ?? 0); @endphp
                        @if ($topContributors->isNotEmpty())
                            <div class="mt-4 space-y-3">
                                @foreach ($topContributors as $row)
                                    @php $name = $row->causer->name ?? 'System'; @endphp
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-7 w-7 flex-none items-center justify-center rounded-full bg-brand-100 text-[11px] font-semibold text-brand-700">
                                            {{ Str::of($name)->explode(' ')->take(2)->map(fn ($p) => Str::substr($p, 0, 1))->implode('') }}
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="truncate text-xs font-medium text-slate-700">{{ $name }}</span>
                                                <span class="flex-none text-xs font-medium tabular text-slate-500">{{ number_format($row->c) }}</span>
                                            </div>
                                            <span class="mt-1 block h-1.5 overflow-hidden rounded-full bg-slate-100">
                                                <span class="block h-full rounded-full bg-brand-500" style="width: {{ max(4, round($row->c / $maxContrib * 100)) }}%"></span>
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="py-6 text-center text-sm text-slate-400">No contributors yet.</p>
                        @endif
                    </div>

                    {{-- Activity by type --}}
                    <div class="card card-pad">
                        <h3 class="text-sm font-semibold text-slate-900">By type</h3>
                        @php $maxType = max(1, $byType->max('count') ?? 0); @endphp
                        @if ($byType->isNotEmpty())
                            <div class="mt-4 space-y-3">
                                @foreach ($byType as $row)
                                    <div>
                                        <div class="flex items-center justify-between gap-2 text-xs">
                                            <span class="truncate font-medium text-slate-700">{{ $row['label'] }}</span>
                                            <span class="flex-none tabular text-slate-500">{{ number_format($row['count']) }}</span>
                                        </div>
                                        <span class="mt-1 block h-1.5 overflow-hidden rounded-full bg-slate-100">
                                            <span class="block h-full rounded-full bg-slate-400" style="width: {{ max(4, round($row['count'] / $maxType * 100)) }}%"></span>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="py-6 text-center text-sm text-slate-400">Nothing tracked yet.</p>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
