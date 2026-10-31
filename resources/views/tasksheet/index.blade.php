<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-4">
                <h2 class="page-title">Tasksheet</h2>

                @if ($team)
                    {{-- Team switcher (viewer's teams listed first) --}}
                    <form method="GET" action="{{ route('tasksheet.index') }}">
                        <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                        <select name="team" onchange="this.form.submit()" aria-label="Team"
                                class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($teams as $t)
                                <option value="{{ $t->id }}" @selected($team->id === $t->id)>{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </form>

                    {{-- Day navigation --}}
                    <div class="flex items-center gap-1">
                        <a href="{{ route('tasksheet.index', ['team' => $team->id, 'date' => $prev->toDateString()]) }}"
                           class="btn-secondary btn-sm !px-2" aria-label="Previous day">‹</a>
                        <form method="GET" action="{{ route('tasksheet.index') }}">
                            <input type="hidden" name="team" value="{{ $team->id }}">
                            <input type="date" name="date" value="{{ $day->toDateString() }}" onchange="this.form.submit()"
                                   class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        </form>
                        <a href="{{ route('tasksheet.index', ['team' => $team->id, 'date' => $next->toDateString()]) }}"
                           class="btn-secondary btn-sm !px-2" aria-label="Next day">›</a>
                        <a href="{{ route('tasksheet.index', ['team' => $team->id]) }}" class="btn-ghost btn-sm ml-1">Today</a>
                    </div>
                @endif
            </div>

            <span class="text-sm font-medium text-slate-600">{{ $day->format('l, M j, Y') }}{{ $isToday ? ' · Today' : '' }}</span>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-6">
            @if (! $team)
                <div class="card p-12 text-center text-sm text-slate-500">No teams yet — create a team to start a tasksheet.</div>
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

                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    @include('partials.stat-tile', ['label' => 'Submitted', 'value' => $tsSubmitted, 'sub' => 'of '.$allRowUsers->count().' '.\Illuminate\Support\Str::plural('member', $allRowUsers->count()), 'icon' => 'list', 'tone' => 'emerald'])
                    @include('partials.stat-tile', ['label' => 'On leave', 'value' => $tsOnLeave, 'sub' => 'casual or sick', 'icon' => 'pause', 'tone' => 'amber'])
                    @include('partials.stat-tile', ['label' => 'Pending', 'value' => $tsPending, 'sub' => 'not filled yet', 'icon' => 'user', 'tone' => 'slate'])
                    @include('partials.stat-tile', ['label' => 'Work points', 'value' => $tsWorkPoints, 'sub' => $tsTickets.' '.\Illuminate\Support\Str::plural('ticket', $tsTickets).' logged', 'icon' => 'activity', 'tone' => 'brand'])
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

                {{-- Filters and export sit in one card but are two separate GET
                     forms — the export needs its own date range, and forms
                     cannot nest. --}}
                <div class="card card-pad flex flex-wrap items-end gap-3">
                <form method="GET" action="{{ route('tasksheet.index') }}" class="flex flex-wrap items-end gap-3">
                    <input type="hidden" name="team" value="{{ $team->id }}">
                    <input type="hidden" name="date" value="{{ $day->toDateString() }}">

                    <div>
                        <label for="f-attendance" class="field-label !mt-0 text-slate-500">Standup</label>
                        <select id="f-attendance" name="attendance" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Any</option>
                            <option value="attended" @selected($filters['attendance'] === 'attended')>Attended</option>
                            <option value="missed" @selected($filters['attendance'] === 'missed')>Did not attend</option>
                            <option value="unmarked" @selected($filters['attendance'] === 'unmarked')>Not yet marked</option>
                        </select>
                    </div>

                    <div>
                        <label for="f-fill" class="field-label !mt-0 text-slate-500">Sheet</label>
                        <select id="f-fill" name="fill" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Any</option>
                            <option value="complete" @selected($filters['fill'] === 'complete')>Complete</option>
                            <option value="partial" @selected($filters['fill'] === 'partial')>Partially filled</option>
                            <option value="empty" @selected($filters['fill'] === 'empty')>Empty</option>
                        </select>
                    </div>

                    <div>
                        <label for="f-leave" class="field-label !mt-0 text-slate-500">Leave</label>
                        <select id="f-leave" name="leave" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Any</option>
                            <option value="working" @selected($filters['leave'] === 'working')>Working</option>
                            <option value="any" @selected($filters['leave'] === 'any')>On leave</option>
                            @foreach (\App\Models\TasksheetEntry::LEAVE_TYPES as $val => $label)
                                <option value="{{ $val }}" @selected($filters['leave'] === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="f-member" class="field-label !mt-0 text-slate-500">Member</label>
                        <select id="f-member" name="member" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Everyone</option>
                            @foreach ($allRowUsers as $u)
                                <option value="{{ $u->id }}" @selected($filters['member'] === $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <button class="btn-secondary btn-sm">Apply</button>
                        @if ($hasFilters)
                            <a href="{{ route('tasksheet.index', ['team' => $team->id, 'date' => $day->toDateString()]) }}" class="btn-ghost btn-sm">Clear all</a>
                        @endif
                    </div>
                </form>

                {{-- Export: defaults to the viewed day, but the range is editable
                     here — the sheet itself is always one day, so this is the
                     only place a team-wide span can be chosen. --}}
                <div class="relative ml-auto" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                    <button type="button" @click="open = !open" class="btn-secondary btn-sm">
                        Export PDF
                        <x-icon name="chevron-down" class="ml-1 h-3 w-3" />
                    </button>

                    <form method="GET" action="{{ route('tasksheet.report') }}" x-show="open" x-transition style="display: none;"
                          class="absolute right-0 z-20 mt-2 w-64 space-y-3 rounded-xl border border-slate-200 bg-white p-4 shadow-lg">
                        <input type="hidden" name="team" value="{{ $team->id }}">
                        {{-- The filters in effect travel with the download. --}}
                        @foreach (['attendance', 'fill', 'leave', 'member'] as $key)
                            @if ($filters[$key])
                                <input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">
                            @endif
                        @endforeach

                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date range</p>

                        <div>
                            <label for="report-from" class="mb-1 block text-[11px] font-medium text-slate-500">From</label>
                            <input id="report-from" type="date" name="from" value="{{ $day->toDateString() }}"
                                   class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div>
                            <label for="report-to" class="mb-1 block text-[11px] font-medium text-slate-500">To</label>
                            <input id="report-to" type="date" name="to" value="{{ $day->toDateString() }}"
                                   class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>

                        <p class="text-[11px] text-slate-400">Defaults to this day. A reversed range is swapped, not rejected.</p>
                        <button class="btn-primary btn-sm w-full justify-center">Download</button>
                    </form>
                </div>
                </div>

                <div class="card overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Member</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500" title="Attended the daily standup">Standup</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Task Plan at Morning</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Day End Result</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Comment</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Work Points</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tickets</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Ticket Count</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Ticket Points</th>
                                @if (auth()->user()->isLead())
                                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Feedback</th>
                                @endif
                                <th class="px-3 py-3"></th>
                            </tr>
                        </thead>
                        @if ($rowUsers->isEmpty())
                            <tbody>
                                <tr>
                                    <td colspan="{{ auth()->user()->isLead() ? 11 : 10 }}" class="px-3 py-12 text-center text-sm text-slate-500">
                                        @if ($hasFilters)
                                            No rows match these filters.
                                            <a href="{{ route('tasksheet.index', ['team' => $team->id, 'date' => $day->toDateString()]) }}"
                                               class="text-brand-600 hover:underline">Clear filters</a>.
                                        @else
                                            No developers or QA on this team{{ $isPast ? ' and no entries for this day' : '' }}.
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
            @endif
        </div>
    </div>
</x-app-layout>
