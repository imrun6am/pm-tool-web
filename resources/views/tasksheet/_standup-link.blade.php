{{--
    The way from a team's tasksheet to that day's standup note.
    @include('tasksheet._standup-link', ['team' => $team, 'day' => $day])
--}}
@php
    $hasStandupDay = \App\Support\WorkingWeek::isWorkingDay($day) && ! $day->isFuture();
    $standupNoteId = $hasStandupDay
        ? \App\Models\DailyStandup::forTeamOn($team, $day)->value('meeting_note_id')
        : null;
    $canGenerateStandup = $hasStandupDay && auth()->user()->can('generate', [\App\Models\DailyStandup::class, $team]);
@endphp
@if ($standupNoteId || $canGenerateStandup)
    <section class="card flex flex-wrap items-center justify-between gap-3 px-5 py-3" aria-label="Standup">
        <p class="flex items-center gap-2 text-sm">
            <x-icon name="user-group" variant="outline" class="h-4 w-4 text-sky-700" />
            <span class="font-semibold text-slate-800">Standup</span>
            <span class="text-xs text-slate-500">{{ $team->name }} · {{ $day->format('D, M j') }}</span>
        </p>
        @if ($standupNoteId)
            <a href="{{ route('meeting-notes.show', $standupNoteId) }}" class="btn-secondary btn-sm">Open standup</a>
        @else
            <form method="POST" action="{{ route('standups.store') }}">
                @csrf
                <input type="hidden" name="team_id" value="{{ $team->id }}">
                <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                <button class="btn-secondary btn-sm">Generate standup</button>
            </form>
        @endif
    </section>
@endif
