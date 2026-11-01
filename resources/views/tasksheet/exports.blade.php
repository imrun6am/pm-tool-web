<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Tasksheet exports"
                       description="PDF reports of the tasksheet, built in the background a few days at a time, so even a wide range never stalls the page. Only you can see your exports."
                       :breadcrumbs="['Tasksheet' => route('tasksheet.index'), 'Exports' => null]" />
    </x-slot>

    @php
        // Literal classes here (not in the JS module) so Tailwind's scanner keeps them.
        $badgeTones = [
            \App\Models\TasksheetExport::STATUS_PENDING => 'badge-slate',
            \App\Models\TasksheetExport::STATUS_PROCESSING => 'badge-info',
            \App\Models\TasksheetExport::STATUS_READY => 'badge-success',
            \App\Models\TasksheetExport::STATUS_FAILED => 'badge-danger',
        ];
        $prefillLabels = \App\Models\TasksheetExport::filterLabels($prefill['filters']);
        $viewer = auth()->user();
    @endphp

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-4">
            {{-- New export. Prefilled from wherever the user came from (the
                 daily sheet, a member's history, or an old report link). --}}
            <form method="POST" action="{{ route('tasksheet.exports.store') }}" class="card p-4 sm:p-5">
                @csrf
                @foreach ($prefill['filters'] as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_10rem_10rem_auto] lg:items-end">
                    <x-field label="Team" for="export-team" name="team">
                        <select id="export-team" name="team" class="field-select" @invalid('team')>
                            <option value="">All teams</option>
                            @foreach ($teams as $t)
                                <option value="{{ $t->id }}" @selected((int) old('team', $prefill['team_id']) === $t->id)>{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="Member" for="export-member" name="member">
                        @if ($isLeadViewer)
                            <select id="export-member" name="member" class="field-select" @invalid('member')>
                                <option value="">Everyone</option>
                                @foreach ($members as $m)
                                    <option value="{{ $m->id }}" @selected((int) old('member', $prefill['member_id']) === $m->id)>
                                        {{ $m->name }}{{ $m->trashed() ? ' (deleted)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            {{-- A member exports only their own rows. --}}
                            <input type="hidden" name="member" value="{{ $viewer->id }}">
                            <div id="export-member" class="field-input flex items-center bg-slate-50 text-slate-600">{{ $viewer->name }} (you)</div>
                        @endif
                    </x-field>

                    <x-field label="From" for="export-from" name="from">
                        <input type="date" id="export-from" name="from" class="field-input" required
                               value="{{ old('from', $prefill['from']) }}" @invalid('from')>
                    </x-field>
                    <x-field label="To" for="export-to" name="to">
                        <input type="date" id="export-to" name="to" class="field-input" required
                               value="{{ old('to', $prefill['to']) }}" @invalid('to')>
                    </x-field>

                    <button type="submit" class="btn-primary" @disabled(! $hasCapacity)>
                        <x-icon name="document-text" variant="outline" class="h-4 w-4" />
                        Generate PDF
                    </button>
                </div>

                @if ($prefillLabels)
                    <div class="mt-3 flex flex-wrap items-center gap-1.5 text-xs">
                        <span class="text-slate-500">Row filters:</span>
                        @foreach ($prefillLabels as $label)
                            <span class="badge badge-brand">{{ $label }}</span>
                        @endforeach
                        <a href="{{ route('tasksheet.exports.index', array_filter([
                                'team' => $prefill['team_id'], 'member' => $prefill['member_id'],
                                'from' => $prefill['from'], 'to' => $prefill['to'],
                            ])) }}" class="ml-1 font-medium text-brand-700 hover:underline">Clear</a>
                    </div>
                @endif

                <p class="mt-3 text-xs text-slate-500">
                    @if ($isLeadViewer)
                        Includes the lead-only feedback column. A reversed range is swapped, not rejected.
                    @else
                        Your own rows only; lead feedback is never included.
                    @endif
                    @unless ($hasCapacity)
                        <span class="font-medium text-warning-800">You have the maximum number of exports in progress — start another when one finishes.</span>
                    @endunless
                </p>
            </form>

            {{-- History, with live status while anything is still running --}}
            <div class="card overflow-x-auto"
                 x-data="tasksheetExports(@js($statuses), @js(route('tasksheet.exports.status')), @js($badgeTones))">
                @if ($exports->isEmpty())
                    <x-empty-state icon="document-text" title="No exports yet"
                                   description="Pick a team, a member and a date range above, or use Export PDF on the tasksheet." />
                @else
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500">Report</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500">Period</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500">Requested</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500">Status</th>
                                <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($exports as $export)
                                @php $id = $export->id; @endphp
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-800">
                                            {{ $export->team_id ? ($export->team?->name ?? 'Deleted team') : 'All teams' }}
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            {{ $export->member_id ? ($export->subject?->name ?? 'Deleted member') : 'Everyone' }}
                                        </div>
                                        @php $labels = \App\Models\TasksheetExport::filterLabels($export->rowFilters()); @endphp
                                        @if ($labels || $export->include_feedback)
                                            <div class="mt-1.5 flex flex-wrap gap-1">
                                                @foreach ($labels as $label)
                                                    <span class="badge badge-brand">{{ $label }}</span>
                                                @endforeach
                                                @if ($export->include_feedback)
                                                    <span class="badge badge-violet" title="Contains the lead-only feedback column">Lead feedback</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                        @if ($export->date_from->equalTo($export->date_to))
                                            {{ $export->date_from->format('D, j M Y') }}
                                        @else
                                            {{ $export->date_from->format('j M Y') }} – {{ $export->date_to->format('j M Y') }}
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-500">
                                        {{ $export->requester?->name ?? '—' }}
                                        <span class="block text-xs text-slate-400" title="{{ $export->created_at->format('M j, Y H:i') }}">{{ $export->created_at->diffForHumans() }}</span>
                                    </td>
                                    <td class="min-w-[13rem] px-4 py-3">
                                        <span class="badge" x-bind:class="tone({{ $id }})">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
                                            <span x-text="row({{ $id }}).status_label">{{ $export->statusLabel() }}</span>
                                        </span>

                                        <div x-show="running({{ $id }})" @style(['display: none' => ! $export->isRunning()]) class="mt-2 max-w-[14rem]">
                                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100" role="progressbar"
                                                 aria-valuemin="0" aria-valuemax="100" aria-label="Export progress"
                                                 x-bind:aria-valuenow="row({{ $id }}).progress">
                                                <div class="h-full rounded-full bg-brand-500 transition-[width] duration-500 ease-out"
                                                     style="width: {{ $export->progressPercent() }}%"
                                                     x-bind:style="{ width: row({{ $id }}).progress + '%' }"></div>
                                            </div>
                                            <p class="mt-1 text-xs tabular text-slate-500" x-text="progressText({{ $id }})"></p>
                                        </div>

                                        <p x-show="row({{ $id }}).status === 'ready'" @style(['display: none' => ! $export->isReady()])
                                           class="mt-1 text-xs tabular text-slate-400"
                                           x-text="rowsText({{ $id }})"></p>

                                        <p x-show="row({{ $id }}).error" @style(['display: none' => ! $export->error])
                                           class="mt-1 max-w-xs text-xs text-danger-700" x-text="row({{ $id }}).error">{{ $export->error }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <span x-show="row({{ $id }}).status === 'ready'" @style(['display: none' => ! $export->isReady()])>
                                                <x-action-btn icon="download" tone="brand" :href="route('tasksheet.exports.download', $export)"
                                                              data-turbo="false" title="Download PDF" aria-label="Download PDF" />
                                            </span>

                                            <form method="POST" action="{{ route('tasksheet.exports.retry', $export) }}"
                                                  x-show="row({{ $id }}).status === 'failed'" @style(['display: none' => ! $export->isFailed()])>
                                                @csrf
                                                <x-action-btn icon="restore" tone="brand" title="Retry export" aria-label="Retry export" />
                                            </form>

                                            <form method="POST" action="{{ route('tasksheet.exports.destroy', $export) }}"
                                                  data-confirm="{{ $export->isRunning() ? 'The export stops and its partial files are removed.' : 'The PDF file is removed. You can generate it again any time.' }}"
                                                  data-confirm-title="{{ $export->isRunning() ? 'Cancel this export?' : 'Delete this export?' }}"
                                                  data-confirm-verb="{{ $export->isRunning() ? 'Cancel export' : 'Delete' }}">
                                                @csrf @method('DELETE')
                                                <x-action-btn icon="trash" tone="rose" title="Delete export" aria-label="Delete export" />
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
                Exports are kept for {{ config('tasksheet.export.retention_days') }} days.
                The queue worker must be running (<code class="font-mono">php artisan queue:work</code>).
            </p>
        </div>
    </div>
</x-app-layout>
