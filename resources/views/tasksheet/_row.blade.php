@php
    /** @var \App\Models\User $rowUser */
    /** @var \App\Models\TasksheetEntry|null $entry */
    $viewer = auth()->user();
    $isLeadViewer = $viewer->isLead();
    // Own row is editable only while still on the team (ex-members are read-only).
    $canEdit = $isLeadViewer || ($viewer->id === $rowUser->id && ($viewerIsMember ?? false));
    $taskCols = 7; // plan, result, comment, work points, tickets, ticket count, ticket points
@endphp
<tbody x-data="{ editing: false }" class="divide-y divide-slate-100 border-t border-slate-100">
    <tr x-show="!editing" class="align-top">
        <td class="px-3 py-3">
            @if ($isLeadViewer || $viewer->id === $rowUser->id)
                <a href="{{ route('tasksheet.user', $rowUser) }}" class="font-medium text-slate-800 hover:text-brand-700">{{ $rowUser->name }}</a>@include('partials.user-tag', ['tagUser' => $rowUser])
            @else
                <span class="font-medium text-slate-800">{{ $rowUser->name }}</span>@include('partials.user-tag', ['tagUser' => $rowUser])
            @endif
            <div class="text-xs text-slate-400">{{ $rowUser->roleLabel() }}</div>
            @if ($entry?->isHalfDay())
                <div class="mt-1 inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">Half day</div>
            @endif
            @if ($entry?->wasFilledLate())
                <div class="mt-1 inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700"
                     title="This row was filled in after the day had passed">Not added on the operating day</div>
            @endif
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
                    <input type="checkbox" name="attended" value="1" onchange="this.form.submit()"
                           @checked($entry?->attendedStandup())
                           title="{{ $entry?->isStandupUnmarked() ?? true ? 'Not yet marked' : ($entry->attendedStandup() ? 'Attended standup' : 'Did not attend standup') }}"
                           aria-label="Standup attendance for {{ $rowUser->name }}"
                           class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                </form>
                @if ($entry?->missedStandup())
                    <div class="mt-0.5 text-[10px] font-medium text-rose-600">Missed</div>
                @elseif ($entry?->isStandupUnmarked() ?? true)
                    <div class="mt-0.5 text-[10px] text-slate-300">Unmarked</div>
                @endif
            @elseif ($entry?->attendedStandup())
                <span class="text-xs font-medium text-emerald-600" title="Attended standup">Yes</span>
            @elseif ($entry?->missedStandup())
                <span class="text-xs font-medium text-rose-600" title="Did not attend standup">No</span>
            @else
                <span class="text-xs text-slate-300">—</span>
            @endif
        </td>

        @if ($entry && $entry->isFullDayLeave())
            <td colspan="{{ $taskCols }}" class="px-3 py-3">
                <span class="inline-flex rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-medium text-sky-700">{{ $entry->leaveLabel() }}</span>
            </td>
        @elseif ($entry)
            <td class="max-w-xs px-3 py-3 text-slate-700">@if ($entry->plan)<div class="prose-notes text-sm">{!! $entry->html('plan') !!}</div>@else — @endif</td>
            <td class="max-w-xs px-3 py-3 text-slate-700">@if ($entry->result)<div class="prose-notes text-sm">{!! $entry->html('result') !!}</div>@else — @endif</td>
            <td class="max-w-xs px-3 py-3 text-slate-600">@if ($entry->comment)<div class="prose-notes text-sm">{!! $entry->html('comment') !!}</div>@else — @endif</td>
            <td class="px-3 py-3 text-center text-slate-700">{{ $entry->work_points ?? '—' }}</td>
            <td class="max-w-[10rem] px-3 py-3 text-slate-600">@if ($entry->tickets)<div class="prose-notes text-sm">{!! $entry->html('tickets') !!}</div>@else — @endif</td>
            <td class="px-3 py-3 text-center text-slate-700">{{ $entry->ticket_count ?? '—' }}</td>
            <td class="px-3 py-3 text-center text-slate-700">{{ $entry->ticket_points ?? '—' }}</td>
        @elseif ($isPast)
            <td colspan="{{ $taskCols }}" class="px-3 py-3">
                <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-medium text-rose-600">Absent — not filled</span>
            </td>
        @else
            <td colspan="{{ $taskCols }}" class="px-3 py-3 text-sm text-slate-300">Not filled yet</td>
        @endif

        @if ($isLeadViewer)
            <td class="max-w-xs px-3 py-3 text-slate-600">@if ($entry?->feedback)<div class="prose-notes text-sm">{!! $entry->html('feedback') !!}</div>@else — @endif</td>
        @endif

        <td class="px-3 py-3 text-right">
            @if ($canEdit)
                <button type="button" @click="editing = true" class="btn-secondary btn-sm">Edit</button>
            @endif
        </td>
    </tr>

    @if ($canEdit)
        <tr x-show="editing" style="display: none;">
            {{-- member + standup + task columns + optional feedback + actions --}}
            <td colspan="{{ 3 + $taskCols + ($isLeadViewer ? 1 : 0) }}" class="bg-slate-50 px-4 py-4">
                <form method="POST" action="{{ route('tasksheet.entries.upsert') }}" class="space-y-4">
                    @csrf @method('PUT')
                    <input type="hidden" name="team_id" value="{{ $team->id }}">
                    <input type="hidden" name="user_id" value="{{ $rowUser->id }}">
                    <input type="hidden" name="date" value="{{ $day->toDateString() }}">

                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-sm font-semibold text-slate-800">{{ $rowUser->name }} — {{ $day->format('M j, Y') }}</span>
                        <select name="leave_type" aria-label="Attendance"
                                class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Working</option>
                            @foreach (\App\Models\TasksheetEntry::LEAVE_TYPES as $val => $label)
                                <option value="{{ $val }}" @selected($entry?->leave_type === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="text-xs text-slate-400">A full-day leave clears the day's tasks; half day keeps them.</span>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="field-label">Task plan at morning</label>
                            <input id="ts-{{ $rowUser->id }}-plan" type="hidden" name="plan" value="{{ $entry?->plan }}">
                            <trix-editor input="ts-{{ $rowUser->id }}-plan" class="prose-notes"></trix-editor>
                        </div>
                        <div>
                            <label class="field-label">Day end result</label>
                            <input id="ts-{{ $rowUser->id }}-result" type="hidden" name="result" value="{{ $entry?->result }}">
                            <trix-editor input="ts-{{ $rowUser->id }}-result" class="prose-notes"></trix-editor>
                        </div>
                        <div>
                            <label class="field-label">Comment</label>
                            <input id="ts-{{ $rowUser->id }}-comment" type="hidden" name="comment" value="{{ $entry?->comment }}">
                            <trix-editor input="ts-{{ $rowUser->id }}-comment" class="prose-notes"></trix-editor>
                        </div>
                        <div>
                            <label class="field-label">Tickets</label>
                            <input id="ts-{{ $rowUser->id }}-tickets" type="hidden" name="tickets" value="{{ $entry?->tickets }}">
                            <trix-editor input="ts-{{ $rowUser->id }}-tickets" class="prose-notes"></trix-editor>
                        </div>
                    </div>

                    <div class="grid max-w-md grid-cols-3 gap-3">
                        <div>
                            <label class="field-label">Work points</label>
                            <input type="number" name="work_points" min="0" value="{{ $entry?->work_points }}" class="field-input">
                        </div>
                        <div>
                            <label class="field-label">Ticket count</label>
                            <input type="number" name="ticket_count" min="0" value="{{ $entry?->ticket_count }}" class="field-input">
                        </div>
                        <div>
                            <label class="field-label">Ticket points</label>
                            <input type="number" name="ticket_points" min="0" value="{{ $entry?->ticket_points }}" class="field-input">
                        </div>
                    </div>

                    @if ($isLeadViewer)
                        <div>
                            <label class="field-label">Feedback <span class="font-normal text-slate-400">(visible to leads only)</span></label>
                            <input id="ts-{{ $rowUser->id }}-feedback" type="hidden" name="feedback" value="{{ $entry?->feedback }}">
                            <trix-editor input="ts-{{ $rowUser->id }}-feedback" class="prose-notes"></trix-editor>
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="editing = false" class="btn-ghost btn-sm">Cancel</button>
                        <button class="btn-primary btn-sm">Save row</button>
                    </div>
                </form>
            </td>
        </tr>
    @endif
</tbody>
