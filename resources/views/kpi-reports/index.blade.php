<x-app-layout>
    <x-slot name="header">
        <x-page-header title="KPI reports"
                       description="Per-member KPI review built from the tasksheet. Claude counts tasks and drafts the review notes; generation runs in the background, one member at a time." />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-4">
            @if ($apiKeyMissing)
                <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <x-icon name="exclamation-circle" variant="outline" class="mt-0.5 h-5 w-5 flex-none" />
                    <p>Add <code class="font-mono">ANTHROPIC_API_KEY</code> to <code class="font-mono">.env</code> to enable report generation.</p>
                </div>
            @endif

            {{-- New report --}}
            <form method="POST" action="{{ route('kpi-reports.store') }}" class="card p-4 sm:p-5"
                  x-data="{ scope: @js(old('scope', \App\Models\KpiReport::SCOPE_ALL)) }">
                @csrf
                <div class="mb-4 grid gap-4 sm:grid-cols-3">
                    <x-field label="Scope" for="scope" name="scope">
                        <select id="scope" name="scope" class="field-select" x-model="scope" @invalid('scope')>
                            @foreach (\App\Models\KpiReport::SCOPES as $value => $label)
                                <option value="{{ $value }}" @selected(old('scope', 'all') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field label="Team" for="team_id" name="team_id" x-show="scope === 'team'" x-cloak
                             hint="Only this team’s tasksheet rows are counted.">
                        <select id="team_id" name="team_id" class="field-select" :disabled="scope !== 'team'" @invalid('team_id')>
                            <option value="">Select a team…</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->id }}" @selected((int) old('team_id') === $team->id)>{{ $team->name }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field label="Member" for="member_id" name="member_id" x-show="scope === 'member'" x-cloak
                             hint="All of the member’s teams are included.">
                        <select id="member_id" name="member_id" class="field-select" :disabled="scope !== 'member'" @invalid('member_id')>
                            <option value="">Select a member…</option>
                            @foreach ($scoredMembers as $member)
                                <option value="{{ $member->id }}" @selected((int) old('member_id') === $member->id)>
                                    {{ $member->name }} · {{ \App\Models\User::ROLES[$member->role] ?? $member->role }}
                                </option>
                            @endforeach
                        </select>
                    </x-field>
                </div>
                <div class="grid gap-4 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end">
                    <x-field label="From" for="from" name="from">
                        <input type="date" id="from" name="from" class="field-input" required
                               value="{{ old('from', $defaultFrom) }}" @invalid('from')>
                    </x-field>
                    <x-field label="To" for="to" name="to">
                        <input type="date" id="to" name="to" class="field-input" required
                               value="{{ old('to', $defaultTo) }}" max="{{ today()->toDateString() }}" @invalid('to')>
                    </x-field>
                    <x-field label="Format" for="format" name="format">
                        <select id="format" name="format" class="field-select" @invalid('format')>
                            @foreach (\App\Models\KpiReport::FORMATS as $value => $label)
                                <option value="{{ $value }}" @selected(old('format', 'xlsx') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <button type="submit" class="btn-primary" @disabled($running || $apiKeyMissing)>
                        <x-icon name="document-text" variant="outline" class="h-4 w-4" />
                        Generate
                    </button>
                </div>
                <p class="mt-3 text-xs text-slate-500">
                    Developers and QA only; team leads are not scored. Full-day leave is excluded.
                    @if ($running) A report is in progress — a new one can start when it finishes. @endif
                </p>
            </form>

            {{-- History --}}
            <div class="card overflow-x-auto"
                 @if ($running) x-data x-init="setTimeout(() => window.location.reload(), 15000)" @endif>
                @if ($reports->isEmpty())
                    <x-empty-state icon="document-text" title="No reports yet"
                                   description="Pick a period and a format above to generate the first KPI review." />
                @else
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500">Scope</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500">Period</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500">Format</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500">Members</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500">Requested</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($reports as $report)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-slate-800">{{ $report->coverageLabel() }}</td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ $report->period_start->format('j M Y') }} – {{ $report->period_end->format('j M Y') }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ $report->formatLabel() }}</td>
                                    <td class="px-4 py-3">
                                        @switch($report->status)
                                            @case(\App\Models\KpiReport::STATUS_COMPLETED)
                                                <span class="badge badge-success">Ready</span>
                                                @break
                                            @case(\App\Models\KpiReport::STATUS_FAILED)
                                                <span class="badge badge-danger">Failed</span>
                                                @break
                                            @default
                                                <span class="badge badge-info">{{ $report->status === 'queued' ? 'Queued' : 'Generating' }} · {{ $report->progressPercent() }}%</span>
                                        @endswitch
                                        @if ($report->error)
                                            <p class="mt-1 max-w-xs text-xs text-danger-700">{{ $report->error }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 tabular text-slate-600">
                                        {{ $report->members_done }} / {{ $report->members_total }}
                                        @if ($report->members_failed)
                                            <span class="text-xs text-danger-700">· {{ $report->members_failed }} failed</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-500">
                                        {{ $report->requester?->name ?? '—' }}
                                        <span class="block text-xs text-slate-400">{{ $report->created_at->diffForHumans() }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            @if ($report->isCompleted())
                                                <a href="{{ route('kpi-reports.download', $report) }}" class="btn-secondary btn-sm">Download</a>
                                            @endif
                                            <form method="POST" action="{{ route('kpi-reports.destroy', $report) }}"
                                                  onsubmit="return confirm('{{ $report->isRunning() ? 'Cancel and delete this report?' : 'Delete this report?' }}')">
                                                @csrf @method('DELETE')
                                                <button class="btn-ghost btn-sm !px-2 text-rose-600 hover:bg-rose-50"
                                                        aria-label="{{ $report->isRunning() ? 'Cancel' : 'Delete' }}">
                                                    <x-icon name="trash" class="h-4 w-4" />
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <p class="text-xs text-slate-400">
                Columns marked “draft” are written by Claude and must be reviewed by the Team Lead before use.
                The queue worker must be running (<code class="font-mono">php artisan queue:work</code>).
            </p>
        </div>
    </div>
</x-app-layout>
