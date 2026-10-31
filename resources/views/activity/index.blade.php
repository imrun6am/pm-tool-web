<x-app-layout>
    @php $hasFilters = $filters['causer_id'] || $filters['event']; @endphp

    <x-slot name="header">
        <x-page-header title="Activity" description="Audit trail of every change made across the workspace." />
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
         The height subtracts the top bar + page header (measured: content starts
         ~158px down). On smaller screens it falls back to normal page scroll. --}}
    <div class="py-6 lg:h-[calc(100vh-9.875rem)] lg:overflow-hidden lg:py-6">
        <div class="app-container flex h-full flex-col gap-4">

            {{-- ============================ KPI CARDS ============================ --}}
            <div class="grid shrink-0 grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach ([
                    ['label' => 'Total events', 'value' => $stats['total'], 'sub' => $filters['causer_id'] ? 'For selected user' : 'All recorded activity', 'icon' => 'chart-bar', 'tone' => 'bg-slate-100 text-slate-500'],
                    ['label' => 'Today', 'value' => $stats['today'], 'sub' => number_format($stats['week']).' in the last 7 days', 'icon' => 'clock', 'tone' => 'bg-brand-50 text-brand-600'],
                    ['label' => 'Contributors', 'value' => $stats['contributors'], 'sub' => 'People with logged activity', 'icon' => 'user-group', 'tone' => 'bg-slate-100 text-slate-500'],
                ] as $kpi)
                    <div class="card flex items-center gap-3 px-4 py-3">
                        <span class="flex h-9 w-9 flex-none items-center justify-center rounded-lg {{ $kpi['tone'] }}">
                            <x-icon :name="$kpi['icon']" variant="outline" class="h-[18px] w-[18px]" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-slate-500">{{ $kpi['label'] }}</p>
                            <p class="tabular text-xl font-semibold leading-tight tracking-tight text-slate-900">{{ number_format($kpi['value']) }}</p>
                            <p class="truncate text-xs text-slate-400">{{ $kpi['sub'] }}</p>
                        </div>
                    </div>
                @endforeach

                {{-- Event split --}}
                <div class="card col-span-2 px-4 py-3 lg:col-span-1">
                    <p class="text-xs font-medium text-slate-500">Event breakdown</p>
                    <div class="mt-1.5 space-y-1">
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
            <x-filter-bar :action="route('activity.index')" :active="$hasFilters" :count="$activities->total()" noun="entry" class="shrink-0">
                <label for="causer_id" class="filter-label">User</label>
                <select id="causer_id" name="causer_id" class="filter-select max-w-[13rem]">
                    <option value="">Everyone</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected($filters['causer_id'] === $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>

                <label for="event" class="filter-label">Event</label>
                <select id="event" name="event" class="filter-select">
                    <option value="">All events</option>
                    @foreach (['created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted'] as $val => $label)
                        <option value="{{ $val }}" @selected($filters['event'] === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-filter-bar>

            {{-- ==================== FEED (left) + CHARTS (right) ==================== --}}
            <div class="grid gap-4 lg:min-h-0 lg:flex-1 lg:grid-cols-3">

                {{-- ----------------------- Activity feed ----------------------- --}}
                <div class="lg:col-span-2 lg:flex lg:min-h-0 lg:flex-col">
                    <div class="card lg:flex lg:min-h-0 lg:flex-1 lg:flex-col">
                        <div class="card-header shrink-0">
                            <h3 class="card-title">Activity log</h3>
                            @if ($activities->hasPages())
                                <span class="tabular text-xs text-slate-400">Page {{ $activities->currentPage() }} of {{ $activities->lastPage() }}</span>
                            @endif
                        </div>
                        @if ($activities->isEmpty())
                            <div class="lg:min-h-0 lg:flex-1 lg:overflow-y-auto">
                                @if ($hasFilters)
                                    <x-empty-state icon="funnel" title="No activity matches these filters."
                                                   description="Try another person or event type.">
                                        <a href="{{ route('activity.index') }}" class="btn-secondary btn-sm">Clear all filters</a>
                                    </x-empty-state>
                                @else
                                    <x-empty-state icon="clock" title="No activity recorded yet."
                                                   description="Changes to releases, tasks and other records will appear here as they happen." />
                                @endif
                            </div>
                        @else
                            <div class="px-6 py-2 lg:min-h-0 lg:flex-1 lg:overflow-y-auto">
                                @include('partials.activity-list', ['activities' => $activities])
                            </div>
                        @endif
                    </div>

                    @if ($activities->hasPages())
                        <div class="mt-4 shrink-0">{{ $activities->links() }}</div>
                    @endif
                </div>

                {{-- ------------------------- Charts --------------------------- --}}
                <div class="space-y-4 lg:min-h-0 lg:overflow-y-auto lg:pr-1">

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
