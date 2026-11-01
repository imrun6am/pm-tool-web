@php
    use App\Models\TasksheetEntry;

    /** @var \App\Models\User $rowUser */
    /** @var \App\Models\TasksheetEntry|null $entry */
    $viewer = auth()->user();
    $isLeadViewer = $viewer->isLead();
    // Own row is editable only while still on the team (ex-members are read-only).
    $canEdit = $isLeadViewer || ($viewer->id === $rowUser->id && ($viewerIsMember ?? false));
    $taskCols = 7; // plan, result, comment, work points, tickets, ticket count, ticket points

    // After a failed save, reopen this row's editor with what the user typed.
    $isOldRow = $errors->any() && (int) old('user_id') === $rowUser->id && old('date') === $day->toDateString();
    $val = fn (string $field) => $isOldRow ? old($field) : $entry?->{$field};
    $leave = (string) ($isOldRow ? old('leave_type') : ($entry?->leave_type ?? ''));
    $filled = $entry?->filledFieldCount() ?? 0;
    $fieldCount = count(TasksheetEntry::TASK_FIELDS);
    $idp = 'ts-'.$rowUser->id; // unique per row on the page

    // "Add to board": the team's open releases (ordered by start), or none. Default
    // to the one running on this day, else the next upcoming one, else the most
    // recent, else no release.
    $boardReleases = $boardReleases ?? collect();
    $defaultRelease = $isOldRow
        ? (old('board_release_id') ? (int) old('board_release_id') : null)
        : ($boardReleases->first(fn ($r) => $day->between($r->start_date, $r->end_date))
            ?? $boardReleases->first(fn ($r) => $r->start_date->gt($day))
            ?? $boardReleases->last())?->id;

    $attendance = ['' => ['label' => 'Working', 'hint' => 'Log the day as usual', 'icon' => 'clipboard-check']]
        + collect(TasksheetEntry::LEAVE_TYPES)->mapWithKeys(fn ($label, $val) => [$val => [
            'label' => $label,
            'hint' => in_array($val, TasksheetEntry::FULL_DAY_LEAVE_TYPES, true) ? 'Clears the day’s tasks' : 'Keeps the day’s tasks',
            'icon' => in_array($val, TasksheetEntry::FULL_DAY_LEAVE_TYPES, true) ? 'calendar' : 'clock',
        ]])->all();
@endphp
<tbody x-data="{
           editing: @js($isOldRow),
           leave: @js($leave),
           busy: null,
           submitted(e) {
               // Read the submitter now; flip the busy state after the browser has captured it.
               const action = e.submitter?.dataset.action || 'save';
               setTimeout(() => this.busy = action);
           },
       }"
       x-effect="document.documentElement.classList.toggle('overflow-hidden', editing)"
       class="divide-y divide-slate-100 border-t border-slate-100">
    <tr class="align-top transition-colors hover:bg-slate-50/60">
        <td class="min-w-[12rem] px-3 py-3">
            <div class="flex items-start gap-2.5">
                <x-avatar :user="$rowUser" size="sm" class="mt-0.5" />
                <div class="min-w-0">
                    @if ($isLeadViewer || $viewer->id === $rowUser->id)
                        <a href="{{ route('tasksheet.user', $rowUser) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $rowUser->name }}</a>@include('partials.user-tag', ['tagUser' => $rowUser])
                    @else
                        <span class="font-medium text-slate-900">{{ $rowUser->name }}</span>@include('partials.user-tag', ['tagUser' => $rowUser])
                    @endif
                    <div class="text-xs text-slate-500">{{ $rowUser->roleLabel() }}</div>
                    <div class="mt-1 flex flex-wrap gap-1">
                        @if ($entry?->isHalfDay())
                            <span class="badge badge-warning">Half day</span>
                        @endif
                        @if ($entry?->wasFilledLate())
                            <span class="badge badge-warning" title="This row was filled in after the day had passed">Not added on the operating day</span>
                        @endif
                    </div>
                </div>
            </div>
        </td>

        {{-- Standup verification: leads toggle it straight from the list, and it
             posts on its own so a member's in-progress row is never touched. --}}
        <td class="px-3 py-3 text-center">
            @if ($entry?->isFullDayLeave())
                <span class="text-xs text-slate-300" title="On full-day leave — standup does not apply">—</span>
            @elseif ($isLeadViewer)
                <form method="POST" action="{{ route('tasksheet.standup') }}" class="inline">
                    @csrf
                    <input type="hidden" name="team_id" value="{{ $team->id }}">
                    <input type="hidden" name="user_id" value="{{ $rowUser->id }}">
                    <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                    <input type="hidden" name="attended" value="0">
                    <input type="checkbox" name="attended" value="1" onchange="this.form.requestSubmit()"
                           @checked($entry?->attendedStandup())
                           title="{{ $entry?->isStandupUnmarked() ?? true ? 'Not yet marked' : ($entry->attendedStandup() ? 'Attended standup' : 'Did not attend standup') }}"
                           aria-label="Standup attendance for {{ $rowUser->name }}"
                           class="field-check h-[18px] w-[18px]">
                </form>
                @if ($entry?->missedStandup())
                    <div class="mt-1 text-[11px] font-semibold text-danger-600">Missed</div>
                @elseif ($entry?->isStandupUnmarked() ?? true)
                    <div class="mt-1 text-[11px] text-slate-400">Unmarked</div>
                @endif
            @elseif ($entry?->attendedStandup())
                <span class="badge badge-success" title="Attended standup">Yes</span>
            @elseif ($entry?->missedStandup())
                <span class="badge badge-danger" title="Did not attend standup">No</span>
            @else
                <span class="text-xs text-slate-300">—</span>
            @endif
        </td>

        @if ($entry && $entry->isFullDayLeave())
            <td colspan="{{ $taskCols }}" class="px-3 py-3">
                <span class="badge badge-info"><x-icon name="calendar" variant="outline" class="h-3.5 w-3.5" />{{ $entry->leaveLabel() }}</span>
            </td>
        @elseif ($entry)
            <td class="max-w-xs px-3 py-3 text-slate-700">@if ($entry->plan)<div class="prose-notes text-sm">{!! $entry->html('plan') !!}</div>@else <span class="text-slate-300">—</span> @endif</td>
            <td class="max-w-xs px-3 py-3 text-slate-700">@if ($entry->result)<div class="prose-notes text-sm">{!! $entry->html('result') !!}</div>@else <span class="text-slate-300">—</span> @endif</td>
            <td class="max-w-xs px-3 py-3 text-slate-600">@if ($entry->comment)<div class="prose-notes text-sm">{!! $entry->html('comment') !!}</div>@else <span class="text-slate-300">—</span> @endif</td>
            <td class="px-3 py-3 text-center">
                @if (! is_null($entry->work_points))
                    <span class="tabular inline-flex min-w-[2rem] justify-center rounded-md bg-brand-50 px-1.5 py-0.5 text-sm font-semibold text-brand-700">{{ $entry->work_points }}</span>
                @else <span class="text-slate-300">—</span> @endif
            </td>
            <td class="max-w-[10rem] px-3 py-3 text-slate-600">@if ($entry->tickets)<div class="prose-notes text-sm">{!! $entry->html('tickets') !!}</div>@else <span class="text-slate-300">—</span> @endif</td>
            <td class="tabular px-3 py-3 text-center font-medium text-slate-700">@if (! is_null($entry->ticket_count)){{ $entry->ticket_count }}@else<span class="font-normal text-slate-300">—</span>@endif</td>
            <td class="px-3 py-3 text-center">
                @if (! is_null($entry->ticket_points))
                    <span class="tabular inline-flex min-w-[2rem] justify-center rounded-md bg-emerald-50 px-1.5 py-0.5 text-sm font-semibold text-emerald-700">{{ $entry->ticket_points }}</span>
                @else <span class="text-slate-300">—</span> @endif
            </td>
        @elseif ($isPast)
            <td colspan="{{ $taskCols }}" class="px-3 py-3">
                <span class="badge badge-danger">Absent — not filled</span>
            </td>
        @else
            <td colspan="{{ $taskCols }}" class="px-3 py-3 text-sm italic text-slate-400">Not filled yet</td>
        @endif

        @if ($isLeadViewer)
            <td class="max-w-xs px-3 py-3 text-slate-600">@if ($entry?->feedback)<div class="prose-notes rounded-lg bg-amber-50/70 px-2.5 py-1.5 text-sm ring-1 ring-inset ring-amber-600/10">{!! $entry->html('feedback') !!}</div>@else <span class="text-slate-300">—</span> @endif</td>
        @endif

        <td class="px-3 py-3 text-right">
            @if ($canEdit)
                <button type="button" @click="editing = true"
                        @class(['btn-sm whitespace-nowrap', 'btn-secondary' => $entry, 'btn-primary' => ! $entry])>
                    <x-icon :name="$entry ? 'pencil-square' : 'plus'" variant="outline" class="h-4 w-4" />
                    {{ $entry ? 'Edit' : 'Fill in' }}
                </button>

                {{-- Slide-over editor (teleported so the table's scroll box can't clip it) --}}
                <template x-teleport="body">
                    <div x-show="editing" x-cloak class="fixed inset-0 z-[55] text-left" role="dialog" aria-modal="true"
                         aria-labelledby="{{ $idp }}-title" @keydown.escape.window="editing = false">
                        <div x-show="editing" x-transition.opacity.duration.200ms @click="editing = false"
                             class="absolute inset-0 bg-slate-900/40 backdrop-blur-[2px]" aria-hidden="true"></div>

                        <aside x-show="editing"
                               x-transition:enter="transition duration-200 ease-out motion-reduce:transition-none" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                               x-transition:leave="transition duration-150 ease-in motion-reduce:transition-none" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                               class="absolute inset-y-0 right-0 flex w-full max-w-2xl flex-col bg-white shadow-2xl">
                            <form method="POST" action="{{ route('tasksheet.entries.upsert') }}" class="flex h-full flex-col" @submit="submitted($event)">
                                @csrf @method('PUT')
                                <input type="hidden" name="team_id" value="{{ $team->id }}">
                                <input type="hidden" name="user_id" value="{{ $rowUser->id }}">
                                <input type="hidden" name="date" value="{{ $day->toDateString() }}">

                                {{-- Header --}}
                                <header class="flex items-start gap-3.5 border-b border-slate-200 bg-gradient-to-r from-brand-50 via-violet-50/60 to-white px-6 py-5">
                                    <x-avatar :user="$rowUser" size="lg" class="ring-2 ring-white" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-semibold text-brand-700">{{ $team->name }} · Tasksheet</p>
                                        <h2 id="{{ $idp }}-title" class="truncate text-base font-semibold text-slate-900">{{ $rowUser->name }}</h2>
                                        <p class="text-sm text-slate-500">{{ $day->format('l, F j, Y') }}</p>
                                    </div>
                                    @if ($entry)
                                        <span @class(['badge mt-1 flex-none', 'badge-success' => $filled === $fieldCount, 'badge-brand' => $filled < $fieldCount])>
                                            {{ $filled }}/{{ $fieldCount }} fields
                                        </span>
                                    @endif
                                    <button type="button" @click="editing = false" class="btn-ghost btn-sm btn-icon -mr-2 -mt-1 flex-none text-slate-500" aria-label="Close">
                                        <x-icon name="x-mark" variant="outline" class="h-5 w-5" />
                                    </button>
                                </header>

                                <div class="scroll-area flex-1 space-y-6 overflow-y-auto px-6 py-6">
                                    @if ($isOldRow)
                                        <div class="flex items-start gap-2 rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700 ring-1 ring-inset ring-danger-600/15">
                                            <x-icon name="exclamation-circle" variant="outline" class="mt-0.5 h-4 w-4 flex-none" />
                                            <span>{{ $errors->first() }}</span>
                                        </div>
                                    @endif

                                    {{-- Attendance --}}
                                    <fieldset>
                                        <legend class="field-label">Attendance</legend>
                                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                            @foreach ($attendance as $value => $opt)
                                                <label class="relative flex cursor-pointer flex-col gap-1 rounded-xl border bg-white p-3 transition-colors focus-within:ring-2 focus-within:ring-brand-500/40"
                                                       :class="leave === @js((string) $value) ? 'border-brand-500 bg-brand-50/60 ring-1 ring-brand-500' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'">
                                                    <input type="radio" name="leave_type" value="{{ $value }}" x-model="leave" class="sr-only">
                                                    <x-icon :name="$opt['icon']" variant="outline" class="h-5 w-5"
                                                            x-bind:class="leave === {{ \Illuminate\Support\Js::from((string) $value) }} ? 'text-brand-600' : 'text-slate-400'" />
                                                    <span class="text-sm font-semibold text-slate-900">{{ $opt['label'] }}</span>
                                                    <span class="text-[11px] leading-tight text-slate-500">{{ $opt['hint'] }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>

                                    <div x-show="@js(TasksheetEntry::FULL_DAY_LEAVE_TYPES).includes(leave)" x-cloak
                                         class="flex items-start gap-2 rounded-lg bg-info-50 px-3 py-2.5 text-sm text-info-800 ring-1 ring-inset ring-info-600/15">
                                        <x-icon name="calendar" variant="outline" class="mt-0.5 h-4 w-4 flex-none" />
                                        A full-day leave clears the day's tasks — the fields below won't be saved.
                                    </div>

                                    <div class="space-y-6 transition-opacity" :class="@js(TasksheetEntry::FULL_DAY_LEAVE_TYPES).includes(leave) && 'pointer-events-none opacity-40'"
                                         x-bind:inert="@js(TasksheetEntry::FULL_DAY_LEAVE_TYPES).includes(leave)">
                                        @foreach ([
                                            ['plan', 'Task plan at morning', 'What you plan to work on today.', 'clipboard-check', 'bg-sky-50 text-sky-600'],
                                            ['result', 'Day end result', 'What got done, what moved, what is blocked.', 'check', 'bg-emerald-50 text-emerald-600'],
                                        ] as [$field, $label, $hint, $icon, $chip])
                                            <div>
                                                <label for="{{ $idp }}-{{ $field }}" class="field-label flex items-center gap-2">
                                                    <span class="flex h-6 w-6 items-center justify-center rounded-md {{ $chip }}"><x-icon :name="$icon" variant="outline" class="h-3.5 w-3.5" /></span>
                                                    {{ $label }}
                                                </label>
                                                <p class="field-hint !mt-1">{{ $hint }}</p>
                                                <input id="{{ $idp }}-{{ $field }}" type="hidden" name="{{ $field }}" value="{{ $val($field) }}">
                                                <trix-editor input="{{ $idp }}-{{ $field }}" class="prose-notes"></trix-editor>
                                            </div>
                                        @endforeach

                                        {{-- Metrics --}}
                                        <div class="grid grid-cols-3 gap-3">
                                            @foreach ([
                                                ['work_points', 'Work points', 'chart-bar', 'text-brand-600'],
                                                ['ticket_count', 'Ticket count', 'document-text', 'text-sky-600'],
                                                ['ticket_points', 'Ticket points', 'check', 'text-emerald-600'],
                                            ] as [$field, $label, $icon, $tone])
                                                <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                                                    <label for="{{ $idp }}-{{ $field }}" class="flex items-center gap-1.5 text-xs font-semibold text-slate-600">
                                                        <x-icon :name="$icon" variant="outline" class="h-4 w-4 {{ $tone }}" />{{ $label }}
                                                    </label>
                                                    <input id="{{ $idp }}-{{ $field }}" type="number" name="{{ $field }}" min="0" inputmode="numeric"
                                                           value="{{ $val($field) }}" placeholder="0"
                                                           class="field-input tabular mt-2 text-base font-semibold" @invalid($field)>
                                                </div>
                                            @endforeach
                                        </div>

                                        @foreach ([
                                            ['tickets', 'Tickets', 'Ticket IDs or links you worked on.', 'document-text', 'bg-violet-50 text-violet-600'],
                                            ['comment', 'Comment', 'Anything else worth noting.', 'chat-bubbles', 'bg-slate-100 text-slate-600'],
                                        ] as [$field, $label, $hint, $icon, $chip])
                                            <div>
                                                <label for="{{ $idp }}-{{ $field }}" class="field-label flex items-center gap-2">
                                                    <span class="flex h-6 w-6 items-center justify-center rounded-md {{ $chip }}"><x-icon :name="$icon" variant="outline" class="h-3.5 w-3.5" /></span>
                                                    {{ $label }}<span class="field-optional">Optional</span>
                                                </label>
                                                <p class="field-hint !mt-1">{{ $hint }}</p>
                                                <input id="{{ $idp }}-{{ $field }}" type="hidden" name="{{ $field }}" value="{{ $val($field) }}">
                                                <trix-editor input="{{ $idp }}-{{ $field }}" class="prose-notes"></trix-editor>
                                            </div>
                                        @endforeach
                                    </div>

                                    @if ($isLeadViewer)
                                        <div class="rounded-xl bg-amber-50/70 p-4 ring-1 ring-inset ring-amber-600/15">
                                            <label for="{{ $idp }}-feedback" class="field-label flex items-center gap-2 text-amber-900">
                                                <x-icon name="lock-closed" variant="outline" class="h-4 w-4 text-amber-600" />
                                                Feedback <span class="font-normal text-amber-800/80">(visible to leads only)</span>
                                            </label>
                                            <input id="{{ $idp }}-feedback" type="hidden" name="feedback" value="{{ $val('feedback') }}">
                                            <trix-editor input="{{ $idp }}-feedback" class="prose-notes bg-white"></trix-editor>
                                            @include('ai.partials.feedback-draft', ['idp' => $idp, 'team' => $team ?? null, 'rowUser' => $rowUser, 'day' => $day])
                                        </div>
                                    @endif
                                </div>

                                {{-- Footer --}}
                                <footer class="space-y-3 border-t border-slate-200 bg-slate-50/80 px-6 py-4">
                                    {{-- Add to board: Claude splits the plan into To Do cards for this member --}}
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg bg-white px-3 py-2 ring-1 ring-inset ring-violet-200">
                                        <x-icon name="sparkles" variant="outline" class="h-4 w-4 flex-none text-violet-600" />
                                        <label for="{{ $idp }}-board-release" class="text-xs font-medium text-slate-700">Board release</label>
                                        <select id="{{ $idp }}-board-release" name="board_release_id"
                                                class="field-control field-sm w-auto min-w-0 flex-1 pr-8 sm:max-w-[15rem]" @invalid('board_release_id')>
                                            <option value="" @selected($defaultRelease === null)>No release</option>
                                            @foreach ($boardReleases as $r)
                                                <option value="{{ $r->id }}" @selected($defaultRelease === $r->id)>{{ $r->name }}</option>
                                            @endforeach
                                        </select>
                                        <span class="w-full text-[11px] leading-snug text-slate-500 sm:w-auto sm:flex-1">
                                            Claude splits the plan into tasks assigned to {{ Str::before($rowUser->name, ' ') ?: $rowUser->name }}.
                                        </span>
                                    </div>

                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <button type="button" @click="editing = false" class="btn-ghost mr-auto sm:mr-0">Cancel</button>
                                        <button type="submit" formaction="{{ route('tasksheet.entries.board') }}" data-action="board"
                                                class="btn border border-violet-700/30 bg-gradient-to-b from-violet-500 to-fuchsia-600 text-white shadow-btn hover:from-violet-600 hover:to-fuchsia-700 focus-visible:ring-violet-500/60"
                                                :disabled="busy !== null || @js(TasksheetEntry::FULL_DAY_LEAVE_TYPES).includes(leave)"
                                                :title="@js(TasksheetEntry::FULL_DAY_LEAVE_TYPES).includes(leave) ? 'A full-day leave has no tasks to add' : 'Save the row and add its plan to the board'">
                                            <svg x-show="busy === 'board'" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3" />
                                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                                            </svg>
                                            <x-icon name="sparkles" variant="outline" class="h-4 w-4" x-show="busy !== 'board'" />
                                            <span x-text="busy === 'board' ? 'Creating tasks…' : 'Add to board'">Add to board</span>
                                        </button>
                                        <button type="submit" data-action="save" class="btn-primary" :disabled="busy !== null">
                                            <x-icon name="check" variant="outline" class="h-4 w-4" />Save row
                                        </button>
                                    </div>
                                </footer>
                            </form>
                        </aside>
                    </div>
                </template>
            @endif
        </td>
    </tr>
</tbody>
