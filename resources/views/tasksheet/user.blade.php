@php $isLeadViewer = auth()->user()->isLead(); @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-4">
                <h2 class="page-title">Tasksheet — {{ $member->name }}</h2>
                @include('partials.user-tag', ['tagUser' => $member])
                <span class="text-sm text-slate-400">{{ $member->roleLabel() }}</span>
            </div>

            {{-- Filters: team + date range --}}
            <form method="GET" action="{{ route('tasksheet.user', $member) }}" class="flex flex-wrap items-center gap-1">
                <label for="team" class="field-label !mt-0 !font-normal text-slate-500">Team</label>
                <select id="team" name="team" onchange="this.form.submit()"
                        class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="" @selected(! $teamFilter)>All teams</option>
                    @foreach ($teams as $t)
                        <option value="{{ $t->id }}" @selected($teamFilter === $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>

                <label class="field-label !mt-0 ml-2 !font-normal text-slate-500">Dates</label>
                <input type="date" name="from" value="{{ $from }}" aria-label="From date"
                       class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                <span class="text-slate-400">–</span>
                <input type="date" name="to" value="{{ $to }}" aria-label="To date"
                       class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">

                <label for="f-attendance" class="field-label !mt-0 ml-2 !font-normal text-slate-500">Standup</label>
                <select id="f-attendance" name="attendance"
                        class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">Any</option>
                    <option value="attended" @selected($filters['attendance'] === 'attended')>Attended</option>
                    <option value="missed" @selected($filters['attendance'] === 'missed')>Did not attend</option>
                    <option value="unmarked" @selected($filters['attendance'] === 'unmarked')>Not yet marked</option>
                </select>

                <label for="f-fill" class="field-label !mt-0 ml-2 !font-normal text-slate-500">Sheet</label>
                <select id="f-fill" name="fill"
                        class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">Any</option>
                    <option value="complete" @selected($filters['fill'] === 'complete')>Complete</option>
                    <option value="partial" @selected($filters['fill'] === 'partial')>Partially filled</option>
                    <option value="empty" @selected($filters['fill'] === 'empty')>Empty</option>
                </select>

                <label for="f-leave" class="field-label !mt-0 ml-2 !font-normal text-slate-500">Leave</label>
                <select id="f-leave" name="leave"
                        class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">Any</option>
                    <option value="working" @selected($filters['leave'] === 'working')>Working</option>
                    <option value="any" @selected($filters['leave'] === 'any')>On leave</option>
                    @foreach (\App\Models\TasksheetEntry::LEAVE_TYPES as $val => $label)
                        <option value="{{ $val }}" @selected($filters['leave'] === $val)>{{ $label }}</option>
                    @endforeach
                </select>

                <button class="btn-secondary btn-sm">Apply</button>
                @if ($hasFilters)
                    <a href="{{ route('tasksheet.user', $member) }}" class="btn-ghost btn-sm">Clear</a>
                @endif
                <a href="{{ route('tasksheet.report', array_filter([
                        'member' => $member->id,
                        'team' => $teamFilter,
                        'from' => $from,
                        'to' => $to,
                        'attendance' => $filters['attendance'],
                        'fill' => $filters['fill'],
                        'leave' => $filters['leave'],
                    ])) }}" class="btn-secondary btn-sm">Export PDF</a>
            </form>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            @if ($entries->isEmpty())
                <div class="card p-12 text-center text-sm text-slate-500">No tasksheet entries match.</div>
            @else
                <div class="card overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Team</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500" title="Attended the daily standup">Standup</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Task Plan at Morning</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Day End Result</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Comment</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Work Points</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tickets</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Ticket Count</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Ticket Points</th>
                                @if ($isLeadViewer)
                                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Feedback</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($entries as $entry)
                                <tr class="align-top">
                                    <td class="whitespace-nowrap px-3 py-3">
                                        <a href="{{ route('tasksheet.index', ['team' => $entry->team_id, 'date' => $entry->date->toDateString()]) }}"
                                           class="font-medium text-slate-800 hover:text-brand-700">{{ $entry->date->format('D, M j, Y') }}</a>
                                        @if ($entry->isHalfDay())
                                            <div class="mt-1 inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">Half day</div>
                                        @endif
                                        @if ($entry->wasFilledLate())
                                            <div class="mt-1 inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">Not added on the operating day</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $entry->team->name ?? '—' }}</td>
                                    <td class="px-3 py-3 text-center">
                                        @if ($entry->isFullDayLeave())
                                            <span class="text-xs text-slate-300" title="On full-day leave — standup does not apply">—</span>
                                        @elseif ($entry->attendedStandup())
                                            <span class="text-xs font-medium text-emerald-600">Yes</span>
                                        @elseif ($entry->missedStandup())
                                            <span class="text-xs font-medium text-rose-600">No</span>
                                        @else
                                            <span class="text-xs text-slate-300" title="Not yet marked">—</span>
                                        @endif
                                    </td>
                                    @if ($entry->isFullDayLeave())
                                        <td colspan="7" class="px-3 py-3">
                                            <span class="inline-flex rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-medium text-sky-700">{{ $entry->leaveLabel() }}</span>
                                        </td>
                                    @else
                                        <td class="max-w-xs px-3 py-3 text-slate-700">@if ($entry->plan)<div class="prose-notes text-sm">{!! $entry->html('plan') !!}</div>@else — @endif</td>
                                        <td class="max-w-xs px-3 py-3 text-slate-700">@if ($entry->result)<div class="prose-notes text-sm">{!! $entry->html('result') !!}</div>@else — @endif</td>
                                        <td class="max-w-xs px-3 py-3 text-slate-600">@if ($entry->comment)<div class="prose-notes text-sm">{!! $entry->html('comment') !!}</div>@else — @endif</td>
                                        <td class="px-3 py-3 text-center text-slate-700">{{ $entry->work_points ?? '—' }}</td>
                                        <td class="max-w-[10rem] px-3 py-3 text-slate-600">@if ($entry->tickets)<div class="prose-notes text-sm">{!! $entry->html('tickets') !!}</div>@else — @endif</td>
                                        <td class="px-3 py-3 text-center text-slate-700">{{ $entry->ticket_count ?? '—' }}</td>
                                        <td class="px-3 py-3 text-center text-slate-700">{{ $entry->ticket_points ?? '—' }}</td>
                                    @endif
                                    @if ($isLeadViewer)
                                        <td class="max-w-xs px-3 py-3 text-slate-600">@if ($entry->feedback)<div class="prose-notes text-sm">{!! $entry->html('feedback') !!}</div>@else — @endif</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-slate-400">{{ $entries->count() }} {{ Str::plural('entry', $entries->count()) }}</p>
            @endif
        </div>
    </div>
</x-app-layout>
