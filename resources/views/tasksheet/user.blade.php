@php $isLeadViewer = auth()->user()->isLead(); @endphp
<x-app-layout>
    @php
        $memberTag = $member->statusTag();
        $memberMeta = $member->roleLabel().($memberTag ? ' · '.$memberTag : '');
    @endphp

    <x-slot name="header">
        <x-page-header :title="'Tasksheet — '.$member->name" :description="$memberMeta"
                       :breadcrumbs="['Tasksheet' => route('tasksheet.index'), $member->name => null]">
            <x-slot name="actions">
                <a href="{{ route('tasksheet.report', array_filter([
                        'member' => $member->id,
                        'team' => $teamFilter,
                        'from' => $from,
                        'to' => $to,
                        'attendance' => $filters['attendance'],
                        'fill' => $filters['fill'],
                        'leave' => $filters['leave'],
                    ])) }}" class="btn-secondary btn-sm">Export PDF</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-4">
            {{-- Filters: team + date range + row state --}}
            <x-filter-bar :action="route('tasksheet.user', $member)" :active="$hasFilters" :count="$entries->count()" noun="entry">
                <label for="team" class="filter-label">Team</label>
                <select id="team" name="team" class="filter-select max-w-[13rem]">
                    <option value="" @selected(! $teamFilter)>All teams</option>
                    @foreach ($teams as $t)
                        <option value="{{ $t->id }}" @selected($teamFilter === $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>

                <div class="flex items-center gap-1.5">
                    <label for="from" class="filter-label">From date</label>
                    <input id="from" type="date" name="from" value="{{ $from }}" class="filter-date" title="From">
                    <span class="text-xs text-slate-400" aria-hidden="true">to</span>
                    <label for="to" class="filter-label">To date</label>
                    <input id="to" type="date" name="to" value="{{ $to }}" class="filter-date" title="To">
                </div>

                <label for="f-attendance" class="filter-label">Standup</label>
                <select id="f-attendance" name="attendance" class="filter-select">
                    <option value="">Standup: any</option>
                    <option value="attended" @selected($filters['attendance'] === 'attended')>Attended</option>
                    <option value="missed" @selected($filters['attendance'] === 'missed')>Did not attend</option>
                    <option value="unmarked" @selected($filters['attendance'] === 'unmarked')>Not yet marked</option>
                </select>

                <label for="f-fill" class="filter-label">Sheet</label>
                <select id="f-fill" name="fill" class="filter-select">
                    <option value="">Sheet: any</option>
                    <option value="complete" @selected($filters['fill'] === 'complete')>Complete</option>
                    <option value="partial" @selected($filters['fill'] === 'partial')>Partially filled</option>
                    <option value="empty" @selected($filters['fill'] === 'empty')>Empty</option>
                </select>

                <label for="f-leave" class="filter-label">Leave</label>
                <select id="f-leave" name="leave" class="filter-select">
                    <option value="">Leave: any</option>
                    <option value="working" @selected($filters['leave'] === 'working')>Working</option>
                    <option value="any" @selected($filters['leave'] === 'any')>On leave</option>
                    @foreach (\App\Models\TasksheetEntry::LEAVE_TYPES as $val => $label)
                        <option value="{{ $val }}" @selected($filters['leave'] === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-filter-bar>

            @if ($entries->isEmpty())
                <div class="card">
                    @if ($hasFilters)
                        <x-empty-state icon="funnel" title="No tasksheet entries match."
                                       description="Try widening the date range or removing a filter.">
                            <a href="{{ route('tasksheet.user', $member) }}" class="btn-secondary btn-sm">Clear all filters</a>
                        </x-empty-state>
                    @else
                        <x-empty-state icon="clipboard-check" title="No tasksheet entries yet."
                                       description="Days this member fills in on a team tasksheet will show up here." />
                    @endif
                </div>
            @else
                <div class="card overflow-hidden">
                    <div class="table-wrap">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50/80 text-xs text-slate-500">
                            <tr>
                                <th class="whitespace-nowrap px-3 py-2.5 text-left font-medium">Date</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-left font-medium">Team</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-center font-medium" title="Attended the daily standup">Standup</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-left font-medium">Task Plan at Morning</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-left font-medium">Day End Result</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-left font-medium">Comment</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-center font-medium">Work Points</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-left font-medium">Tickets</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-center font-medium">Ticket Count</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-center font-medium">Ticket Points</th>
                                @if ($isLeadViewer)
                                    <th class="whitespace-nowrap px-3 py-2.5 text-left font-medium">Feedback</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($entries as $entry)
                                <tr class="align-top transition-colors hover:bg-slate-50/70">
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
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
