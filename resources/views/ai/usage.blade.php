<x-app-layout>
    <x-slot name="header">
        <x-page-header title="AI usage"
                       description="Every Claude call the app makes, what it costs, and switches to turn each feature on or off.">
            <x-slot name="actions">
                <form method="GET" action="{{ route('ai.usage') }}" class="flex items-center gap-2">
                    <label for="days" class="sr-only">Period</label>
                    <select id="days" name="days" class="field-select" onchange="this.form.requestSubmit()">
                        @foreach ($periods as $value => $label)
                            <option value="{{ $value }}" @selected($days === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <noscript><button class="btn btn-secondary btn-sm">Apply</button></noscript>
                </form>
            </x-slot>
        </x-page-header>
    </x-slot>

    @php
        $maxDaily = max(array_column($daily, 'calls') ?: [0]);
        $failRate = $totals['calls'] > 0 ? round($totals['failures'] / $totals['calls'] * 100) : 0;
        $money = fn (float $v) => $v > 0 && $v < 0.01 ? '< $0.01' : '$'.number_format($v, 2);
        $statusTone = ['ok' => 'badge-success', 'refused' => 'badge-warning', 'truncated' => 'badge-warning', 'invalid' => 'badge-danger', 'error' => 'badge-danger'];
    @endphp

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-6">
            @unless ($configured)
                <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="status">
                    <x-icon name="exclamation-circle" variant="outline" class="mt-0.5 h-5 w-5 flex-none" />
                    <p>Add <code class="font-mono">ANTHROPIC_API_KEY</code> to <code class="font-mono">.env</code>. Until then every Claude feature answers “not configured”.</p>
                </div>
            @endunless

            <div class="stat-strip">
                <x-stat label="Claude calls" :value="number_format($totals['calls'])" :sub="$periods[$days]" icon="sparkles" tone="violet" />
                <x-stat label="Estimated cost" :value="$money($totals['cost'])" sub="at list prices" icon="chart-bar" tone="emerald" />
                <x-stat label="Tokens" :value="number_format($totals['tokens'])" sub="input + output" icon="document-text" tone="sky" />
                <x-stat label="Failed calls" :value="$failRate.'%'" :sub="$totals['failures'].' of '.$totals['calls']" icon="exclamation-circle"
                        tone="rose" :alert="$failRate >= 10" />
            </div>

            {{-- Daily calls --}}
            <section class="card card-pad" aria-labelledby="ai-daily">
                <h2 id="ai-daily" class="card-title">Calls per day</h2>
                @if ($totals['calls'] === 0)
                    <x-empty-state icon="sparkles" title="No Claude calls yet"
                                   description="Calls appear here as soon as someone uses a Claude feature." class="mt-4" />
                @else
                    <div class="mt-4 flex h-32 items-end gap-[3px]" role="img"
                         aria-label="Claude calls per day, peaking at {{ $maxDaily }}">
                        @foreach ($daily as $d)
                            <div class="group relative flex h-full flex-1 items-end">
                                <div class="w-full rounded-t bg-violet-500/80 transition group-hover:bg-violet-600"
                                     style="height: {{ $maxDaily > 0 ? max(2, $d['calls'] / $maxDaily * 100) : 0 }}%"
                                     title="{{ \Illuminate\Support\Carbon::parse($d['date'])->format('M j') }}: {{ $d['calls'] }} {{ Str::plural('call', $d['calls']) }}"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 flex justify-between text-xs text-slate-500">
                        <span>{{ \Illuminate\Support\Carbon::parse($daily[0]['date'])->format('M j') }}</span>
                        <span>Today</span>
                    </div>
                @endif
            </section>

            {{-- Features --}}
            <section class="card" aria-labelledby="ai-features">
                <div class="card-header">
                    <h2 id="ai-features" class="card-title">Features</h2>
                    <p class="card-subtitle">A feature that is switched off disappears from every page until it is switched back on.</p>
                </div>
                <div class="table-wrap">
                    <table class="table-base">
                        <thead>
                            <tr>
                                <th scope="col">Feature</th>
                                <th scope="col">Model</th>
                                <th scope="col" class="text-right">Calls</th>
                                <th scope="col" class="text-right">Failed</th>
                                <th scope="col" class="text-right">Avg time</th>
                                <th scope="col" class="text-right">Est. cost</th>
                                <th scope="col" class="text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($byFeature as $f)
                                <tr>
                                    <td>
                                        <p class="font-medium text-slate-900">{{ $f['label'] }}</p>
                                        <p class="text-xs text-slate-500">{{ $f['description'] }}</p>
                                    </td>
                                    <td class="whitespace-nowrap font-mono text-xs text-slate-600">{{ $f['model'] }} · {{ $f['effort'] }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($f['calls']) }}</td>
                                    <td @class(['text-right tabular-nums', 'text-danger-600' => $f['failures'] > 0])>{{ $f['failures'] }}</td>
                                    <td class="text-right tabular-nums text-slate-600">{{ $f['avg_seconds'] !== null ? $f['avg_seconds'].' s' : '—' }}</td>
                                    <td class="text-right tabular-nums">{{ $money($f['cost']) }}</td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('ai.features.toggle', $f['key']) }}">
                                            @csrf
                                            <input type="hidden" name="enabled" value="{{ $f['enabled'] ? 0 : 1 }}">
                                            <button type="submit" role="switch" aria-checked="{{ $f['enabled'] ? 'true' : 'false' }}"
                                                    aria-label="{{ $f['enabled'] ? 'Switch off' : 'Switch on' }} {{ $f['label'] }}"
                                                    @class(['relative inline-flex h-6 w-11 flex-none items-center rounded-full transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/50',
                                                            'bg-success-600' => $f['enabled'], 'bg-slate-300' => ! $f['enabled']])>
                                                <span @class(['inline-block h-5 w-5 rounded-full bg-white shadow transition',
                                                              'translate-x-5' => $f['enabled'], 'translate-x-0.5' => ! $f['enabled']])></span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Recent calls --}}
            <section class="card" aria-labelledby="ai-recent">
                <div class="card-header">
                    <h2 id="ai-recent" class="card-title">Recent calls</h2>
                </div>
                @if ($recent->isEmpty())
                    <x-empty-state icon="clock" title="Nothing yet" description="The latest 25 calls show here." class="p-6" />
                @else
                    <div class="table-wrap">
                        <table class="table-base">
                            <thead>
                                <tr>
                                    <th scope="col">When</th>
                                    <th scope="col">Feature</th>
                                    <th scope="col">By</th>
                                    <th scope="col">Outcome</th>
                                    <th scope="col" class="text-right">Tokens in / out</th>
                                    <th scope="col" class="text-right">Time</th>
                                    <th scope="col" class="text-right">Est. cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recent as $log)
                                    <tr>
                                        <td class="whitespace-nowrap text-slate-600" title="{{ $log->created_at }}">{{ $log->created_at->diffForHumans() }}</td>
                                        <td>{{ $byFeature[$log->feature]['label'] ?? $log->feature }}</td>
                                        <td>{{ $log->user?->name ?? 'System' }}</td>
                                        <td>
                                            <span class="badge {{ $statusTone[$log->status] ?? 'badge-slate' }}" @if ($log->error) title="{{ $log->error }}" @endif>
                                                {{ \App\Models\AiUsageLog::STATUSES[$log->status] ?? $log->status }}
                                            </span>
                                        </td>
                                        <td class="text-right tabular-nums">{{ number_format($log->input_tokens) }} / {{ number_format($log->output_tokens) }}</td>
                                        <td class="text-right tabular-nums text-slate-600">{{ number_format($log->duration_ms / 1000, 1) }} s</td>
                                        <td class="text-right tabular-nums">{{ $money($log->estimatedCost() ?? 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
