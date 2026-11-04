{{--
    The generated part of a daily standup: the team's tasksheets for the day.
    Text fields are sanitized HTML copied from tasksheet rows; AI text is plain.
    @include('meeting-notes._standup', ['standup' => $meetingNote->standup])
--}}
@php
    $generated = $standup->content;
    $counts = $generated['counts'] ?? [];
    $previousDay = $standup->previousDate();
    $work = fn (array $fields, string $field) => new \Illuminate\Support\HtmlString($fields[$field] ?? '');
@endphp
<article class="card" aria-labelledby="standup-listing-title">
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
        <div class="min-w-0">
            <h2 id="standup-listing-title" class="text-sm font-semibold text-slate-900">From the tasksheet</h2>
            <p class="mt-0.5 text-sm text-slate-500">
                {{ $counts['filled'] ?? 0 }} of {{ $counts['people'] ?? 0 }} filled in
                <span aria-hidden="true" class="text-slate-300">·</span>
                {{ $counts['on_leave'] ?? 0 }} on leave
                <span aria-hidden="true" class="text-slate-300">·</span>
                updated {{ $standup->generated_at?->format('g:i A') }}
            </p>
        </div>
        <div class="flex flex-none items-center gap-2">
            @if ($standup->team)
                <a href="{{ route('tasksheet.index', ['team' => $standup->team_id, 'date' => $standup->date->toDateString()]) }}" class="btn-ghost btn-sm">Open tasksheet</a>
                @can('generate', [\App\Models\DailyStandup::class, $standup->team])
                    <form method="POST" action="{{ route('standups.store') }}">
                        @csrf
                        <input type="hidden" name="team_id" value="{{ $standup->team_id }}">
                        <input type="hidden" name="date" value="{{ $standup->date->toDateString() }}">
                        <button class="btn-secondary btn-sm">Refresh</button>
                    </form>
                @endcan
            @endif
        </div>
    </header>

    @if (! empty($generated['on_leave']) || ! empty($generated['not_filled']))
        <dl class="space-y-1.5 border-b border-slate-100 px-5 py-3 text-sm sm:px-6">
            @if (! empty($generated['on_leave']))
                <div class="flex flex-wrap gap-x-2">
                    <dt class="font-medium text-slate-700">On leave:</dt>
                    <dd class="text-slate-600">{{ collect($generated['on_leave'])->map(fn ($p) => "{$p['name']} ({$p['leave']})")->join(', ') }}</dd>
                </div>
            @endif
            @if (! empty($generated['not_filled']))
                <div class="flex flex-wrap gap-x-2">
                    <dt class="font-medium text-slate-700">Not filled in yet:</dt>
                    <dd class="text-slate-600">{{ collect($generated['not_filled'])->pluck('name')->join(', ') }}</dd>
                </div>
            @endif
        </dl>
    @endif

    @if (! empty($generated['ai']))
        <section class="border-b border-slate-100 bg-violet-50/50 px-5 py-4 text-sm sm:px-6" aria-label="AI summary">
            <h3 class="flex items-center gap-1.5 text-xs font-semibold text-violet-800">
                <x-icon name="sparkles" variant="outline" class="h-3.5 w-3.5" />
                AI summary of {{ \Illuminate\Support\Carbon::parse($generated['ai']['covers'])->format('D, M j') }}
            </h3>
            <p class="mt-1.5 text-slate-700">{{ $generated['ai']['summary'] }}</p>
            @if (! empty($generated['ai']['blockers']))
                <h4 class="mt-3 text-xs font-semibold text-slate-700">Blockers</h4>
                <ul class="mt-1 list-disc space-y-1 pl-5 text-slate-700">
                    @foreach ($generated['ai']['blockers'] as $blocker)
                        <li>@if ($blocker['member'])<span class="font-medium">{{ $blocker['member'] }}:</span> @endif{{ $blocker['text'] }}</li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    @if (empty($generated['people']))
        <p class="px-5 py-6 text-sm text-slate-500 sm:px-6">Nobody has written anything on the tasksheet for this standup yet.</p>
    @else
        <ul class="divide-y divide-slate-100">
            @foreach ($generated['people'] as $person)
                @php
                    $did = $person['previous'] ?? [];
                    $doing = $person['today'] ?? [];
                @endphp
                <li class="px-5 py-4 sm:px-6">
                    <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-900">
                        <x-avatar :name="$person['name']" size="sm" />
                        {{ $person['name'] }}
                        @if (! empty($doing['leave']))
                            <span class="badge badge-warning">{{ $doing['leave'] }}</span>
                        @endif
                    </p>
                    <div class="mt-3 grid gap-4 md:grid-cols-2">
                        <div class="min-w-0">
                            <h3 class="text-xs font-semibold text-slate-500">Did{{ $previousDay ? ' · '.$previousDay->format('D, M j') : '' }}</h3>
                            @if (empty($did['result']) && empty($did['tickets']) && empty($did['comment']))
                                <p class="mt-1 text-sm text-slate-400">{{ $did['leave'] ?? 'Nothing logged' }}</p>
                            @else
                                @if (! empty($did['result']))
                                    <div class="prose-notes mt-1 text-sm text-slate-700">{!! $work($did, 'result') !!}</div>
                                @endif
                                @if (! empty($did['tickets']))
                                    <h4 class="mt-2 text-xs font-medium text-slate-500">Tickets</h4>
                                    <div class="prose-notes text-sm text-slate-700">{!! $work($did, 'tickets') !!}</div>
                                @endif
                                @if (! empty($did['comment']))
                                    <h4 class="mt-2 text-xs font-medium text-slate-500">Comment</h4>
                                    <div class="prose-notes text-sm text-slate-700">{!! $work($did, 'comment') !!}</div>
                                @endif
                            @endif
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-xs font-semibold text-slate-500">Today · {{ $standup->date->format('D, M j') }}</h3>
                            @if (! empty($doing['plan']))
                                <div class="prose-notes mt-1 text-sm text-slate-700">{!! $work($doing, 'plan') !!}</div>
                            @else
                                <p class="mt-1 text-sm text-slate-400">{{ $doing['leave'] ?? 'No plan yet' }}</p>
                            @endif
                            @if (! empty($doing['result']))
                                <h4 class="mt-2 text-xs font-medium text-slate-500">Done so far</h4>
                                <div class="prose-notes text-sm text-slate-700">{!! $work($doing, 'result') !!}</div>
                            @endif
                            @if (! empty($doing['tickets']))
                                <h4 class="mt-2 text-xs font-medium text-slate-500">Tickets</h4>
                                <div class="prose-notes text-sm text-slate-700">{!! $work($doing, 'tickets') !!}</div>
                            @endif
                            @if (! empty($doing['comment']))
                                <h4 class="mt-2 text-xs font-medium text-slate-500">Comment</h4>
                                <div class="prose-notes text-sm text-slate-700">{!! $work($doing, 'comment') !!}</div>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</article>
