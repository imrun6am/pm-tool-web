<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Tasksheet" :description="$day->format('l, M j, Y').($isToday ? ' · Today' : '')">
            {{-- Only offered when the export would be allowed (TasksheetExportPolicy):
                 leads, or a member filtered down to their own rows. --}}
            @if ($team && auth()->user()->can('create', [\App\Models\TasksheetExport::class, $filters['member']]))
                <x-slot name="actions">
                    {{-- Export: defaults to the viewed day, but the range is editable
                         here — the sheet itself is always one day, so this is the
                         only place a team-wide span can be chosen. --}}
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                        <button type="button" @click="open = !open" class="btn-secondary btn-sm" :aria-expanded="open">
                            Export PDF
                            <x-icon name="chevron-down" class="ml-1 h-3 w-3" />
                        </button>

                        {{-- Queues a background export, then lands on the exports list. --}}
                        <form method="POST" action="{{ route('tasksheet.exports.store') }}" data-turbo="false" x-show="open" x-transition style="display: none;"
                              class="menu-panel absolute right-0 z-20 mt-2 w-64 space-y-3 p-4">
                            @csrf
                            <input type="hidden" name="team" value="{{ $team->id }}">
                            {{-- The filters in effect travel with the export. --}}
                            @foreach (['attendance', 'fill', 'leave', 'member'] as $key)
                                @if ($filters[$key])
                                    <input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">
                                @endif
                            @endforeach

                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date range</p>

                            <div>
                                <label for="report-from" class="field-label">From</label>
                                <input id="report-from" type="date" name="from" value="{{ $day->toDateString() }}" class="field-input field-sm">
                            </div>
                            <div>
                                <label for="report-to" class="field-label">To</label>
                                <input id="report-to" type="date" name="to" value="{{ $day->toDateString() }}" class="field-input field-sm">
                            </div>

                            <p class="text-[11px] text-slate-400">Defaults to this day. A reversed range is swapped, not rejected.</p>
                            <button class="btn-primary btn-sm w-full justify-center">Generate PDF</button>
                            <a href="{{ route('tasksheet.exports.index') }}" class="block text-center text-xs font-medium text-brand-700 hover:underline">View my exports</a>
                        </form>
                    </div>
                </x-slot>
            @endif
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-4">
            @if ($team)
                {{-- Scope: team + day. Switching either resets the row filters below. --}}
                <x-filter-bar :action="route('tasksheet.index')">
                    {{-- Team switcher (viewer's teams listed first) --}}
                    <label for="team" class="filter-label">Team</label>
                    <select id="team" name="team" class="filter-select max-w-[14rem]">
                        @foreach ($teams as $t)
                            <option value="{{ $t->id }}" @selected($team->id === $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>

                    {{-- Day navigation --}}
                    <div class="flex items-center gap-1">
                        <a href="{{ route('tasksheet.index', ['team' => $team->id, 'date' => $prev->toDateString()]) }}"
                           class="btn-secondary btn-sm btn-icon" aria-label="Previous day">‹</a>
                        <label for="date" class="filter-label">Day</label>
                        <input id="date" type="date" name="date" value="{{ $day->toDateString() }}" class="filter-date">
                        <a href="{{ route('tasksheet.index', ['team' => $team->id, 'date' => $next->toDateString()]) }}"
                           class="btn-secondary btn-sm btn-icon" aria-label="Next day">›</a>
                        <a href="{{ route('tasksheet.index', ['team' => $team->id]) }}" class="btn-ghost btn-sm">Today</a>
                    </div>
                </x-filter-bar>

                {{-- Claude: standup digest for this team and day (leads) --}}
                @include('ai.panels.standup-digest', ['team' => $team, 'day' => $day])
            @endif

            @if (! $team)
                <div class="card">
                    <x-empty-state icon="clipboard-check" title="No teams yet."
                                   description="Create a team to start a tasksheet." />
                </div>
            @else
                {{-- Productivity panel --}}
                @php
                    $tsWorkPoints = $entries->sum('work_points');
                    $tsTickets = $entries->sum('ticket_count');
                    $tsOnLeave = $entries->filter(fn ($e) => $e->leave_type)->count();
                    $tsSubmitted = $entries->filter(fn ($e) => ! $e->leave_type)->count();
                    // Totals are deliberately computed from the *unfiltered* roster:
                    // narrowing the list is a lens on the day, not a change to it.
                    $tsPending = max($allRowUsers->count() - $entries->count(), 0);
                    $tMax = collect($trend)->max('wp') ?: 0;
                @endphp

                <div class="stat-strip">
                    <x-stat label="Submitted" :value="$tsSubmitted"
                            :sub="'of '.$allRowUsers->count().' '.\Illuminate\Support\Str::plural('member', $allRowUsers->count())"
                            icon="clipboard-check" tone="emerald" />
                    <x-stat label="On leave" :value="$tsOnLeave" sub="casual or sick" icon="calendar" tone="amber" />
                    <x-stat label="Pending" :value="$tsPending" sub="not filled yet" icon="clock" tone="slate" />
                    <x-stat label="Work points" :value="$tsWorkPoints"
                            :sub="$tsTickets.' '.\Illuminate\Support\Str::plural('ticket', $tsTickets).' logged'"
                            icon="chart-bar" tone="brand" />
                </div>

                {{-- Team output — trailing 14 days --}}
                <div class="card card-pad">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">Team output</h3>
                            <p class="mt-0.5 text-xs text-slate-400">Work points per day · last 14 days</p>
                        </div>
                        <span class="text-xs text-slate-400">{{ $team->name }}</span>
                    </div>

                    @if ($tMax <= 0)
                        <p class="mt-6 text-sm text-slate-400">No work points logged in this window yet.</p>
                    @else
                        <div class="mt-5">
                            <div class="flex h-36 items-end gap-1.5">
                                @foreach ($trend as $t)
                                    <div class="flex h-full flex-1 flex-col items-center justify-end gap-1"
                                         title="{{ $t['dow'] }} {{ $t['label'] }}: {{ $t['wp'] }} work points">
                                        @if ($t['wp'] > 0)
                                            <span class="text-[10px] font-medium text-slate-400 tabular">{{ $t['wp'] }}</span>
                                        @endif
                                        <div class="w-full max-w-[1.5rem] rounded-t-[4px] transition-colors {{ $t['current'] ? 'bg-brand-600 ring-2 ring-brand-200' : 'bg-brand-400 hover:bg-brand-500' }}"
                                             style="height: {{ $tMax > 0 ? max((int) round($t['wp'] / $tMax * 80), $t['wp'] > 0 ? 8 : 0) : 0 }}%"></div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-2 flex gap-1.5">
                                @foreach ($trend as $t)
                                    <div class="flex-1 text-center text-[10px] {{ $t['current'] ? 'font-semibold text-brand-600' : 'text-slate-400' }}">{{ $t['label'] }}</div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Row filters: a lens on the day. The action keeps team + date so
                     "Clear" returns to the unfiltered sheet for the same day. --}}
                <x-filter-bar :action="route('tasksheet.index', ['team' => $team->id, 'date' => $day->toDateString()])" :active="$hasFilters" :count="$rowUsers->count()" noun="member">
                    <input type="hidden" name="team" value="{{ $team->id }}">
                    <input type="hidden" name="date" value="{{ $day->toDateString() }}">

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

                    <label for="f-member" class="filter-label">Member</label>
                    <select id="f-member" name="member" class="filter-select max-w-[13rem]">
                        <option value="">Everyone</option>
                        @foreach ($allRowUsers as $u)
                            <option value="{{ $u->id }}" @selected($filters['member'] === $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </x-filter-bar>

                <div class="card overflow-hidden">
                    <div class="table-wrap">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50/80 text-xs text-slate-500">
                            <tr>
                                <th class="whitespace-nowrap px-3 py-2.5 text-left font-medium">Member</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-center font-medium" title="Attended the daily standup">Standup</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-left font-medium">Task Plan at Morning</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-left font-medium">Day End Result</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-left font-medium">Comment</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-center font-medium">Work Points</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-left font-medium">Tickets</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-center font-medium">Ticket Count</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-center font-medium">Ticket Points</th>
                                @if (auth()->user()->isLead())
                                    <th class="whitespace-nowrap px-3 py-2.5 text-left font-medium">Feedback</th>
                                @endif
                                <th class="px-3 py-2.5" aria-label="Actions"></th>
                            </tr>
                        </thead>
                        @if ($rowUsers->isEmpty())
                            <tbody>
                                <tr>
                                    <td colspan="{{ auth()->user()->isLead() ? 11 : 10 }}" class="p-0">
                                        @if ($hasFilters)
                                            <x-empty-state icon="funnel" title="No rows match these filters."
                                                           description="Try another standup, sheet or leave filter.">
                                                <a href="{{ route('tasksheet.index', ['team' => $team->id, 'date' => $day->toDateString()]) }}" class="btn-secondary btn-sm">Clear all filters</a>
                                            </x-empty-state>
                                        @else
                                            <x-empty-state icon="clipboard-check"
                                                           :title="'No developers or QA on this team'.($isPast ? ' and no entries for this day' : '').'.'" />
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        @else
                            @foreach ($rowUsers as $rowUser)
                                @include('tasksheet._row', ['rowUser' => $rowUser, 'entry' => $entries->get($rowUser->id)])
                            @endforeach
                        @endif
                    </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
