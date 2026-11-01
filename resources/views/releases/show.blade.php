@php
    use App\Models\Release;
    use Illuminate\Support\Str;

    $me = auth()->user();
    // Developers/QA get a restricted view: no planning surfaces (timeline,
    // conflicts, details, off-days, history) and no edit controls.
    $limited = $me->hasLimitedAccess();
    $canManage = $me->canManageReleases();

    // The releases list is a planning view hidden from developers/QA, so
    // their trail starts at the dashboard instead.
    $crumbs = $limited
        ? ['Dashboard' => route('dashboard'), $release->name => null]
        : ['Releases' => route('releases.index'), $release->name => null];

    $state = $release->state();
    $total = max($release->durationInDays(), 1);
    $today = today();
    $elapsed = $release->elapsedPercent();
    $todayOffset = $today->between($release->start_date, $release->end_date)
        ? $release->start_date->diffInDays($today) / $total * 100
        : null;
    $window = $release->start_date->year === $release->end_date->year
        ? $release->start_date->format('M j').' – '.$release->end_date->format('M j, Y')
        : $release->start_date->format('M j, Y').' – '.$release->end_date->format('M j, Y');
    $days = fn (int $n) => $n.' '.Str::plural('day', $n);

    // Where the window stands today, in words.
    $dayOf = (int) $release->start_date->diffInDays($today) + 1;
    $windowNote = match ($state) {
        'completed' => 'Completed '.$release->completed_at->format('M j, Y'),
        'upcoming' => 'Starts in '.$days((int) round($today->diffInDays($release->start_date, true))),
        'overdue' => 'Window ended '.$days((int) round($today->diffInDays($release->end_date, true))).' ago',
        default => 'Day '.$dayOf.' of '.$total.' · '.$days(max($total - $dayOf, 0)).' left',
    };

    // Phase progress: done (ended), current (today inside it), or still to come.
    $phaseState = fn ($phase) => match (true) {
        $release->isComplete() || $today->gt($phase->end_date) => 'done',
        $today->between($phase->start_date, $phase->end_date) => 'current',
        default => 'next',
    };
    $currentPhase = $release->isComplete() ? null : $release->phases->first(fn ($p) => $phaseState($p) === 'current');
    $phasesDone = $release->phases->filter(fn ($p) => $phaseState($p) === 'done')->count();

    $taskPct = $taskStats['total'] > 0 ? (int) round($taskStats['done'] / $taskStats['total'] * 100) : 0;
    $offDays = $release->offDays->count();
@endphp
<x-app-layout sticky-header>
    <x-slot name="header">
        <x-page-header :title="$release->name" :breadcrumbs="$crumbs">
            <x-slot name="actions">
                <a href="{{ route('board.index', ['release_id' => $release->id]) }}" class="btn-secondary btn-sm">
                    <x-icon name="view-columns" variant="outline" class="h-4 w-4" />Board view
                </a>
                @if ($canManage)
                    <a href="{{ route('releases.edit', $release) }}" class="btn-secondary btn-sm">
                        <x-icon name="pencil-square" variant="outline" class="h-4 w-4" />Edit release
                    </a>
                @endif
            </x-slot>
        </x-page-header>

        {{-- At a glance --}}
        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-slate-600">
            <x-release-state :release="$release" />
            <x-color-chip :color="$release->project->color" :label="$release->project->name"
                          :href="$limited ? null : route('projects.show', $release->project)" class="max-w-full" />
            <x-color-chip :color="$release->team->color" :label="$release->team->name"
                          :href="$limited ? null : route('teams.show', $release->team)" class="max-w-full" />
            <span class="tabular inline-flex items-center gap-1.5 whitespace-nowrap">
                <x-icon name="calendar" variant="outline" class="h-4 w-4 text-slate-400" />{{ $window }}
                <span class="text-slate-400">· {{ $days($release->durationInDays()) }}</span>
            </span>
            <span class="badge badge-slate tabular">{{ $release->year }} {{ $release->quarterLabel() }}</span>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-6">

            {{-- ============ Planning band: conflicts + timeline ============
                 Hidden from developers/QA (no timeline view). --}}
            @unless ($limited)
                @if ($conflicts->isNotEmpty())
                    <div class="flex items-start gap-3 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3.5 text-sm text-warning-900 sm:px-5" role="status">
                        <x-icon name="exclamation-circle" variant="outline" class="mt-px h-5 w-5 flex-none text-warning-600" />
                        <div class="min-w-0">
                            <p class="font-medium">{{ $release->team->name }} is double-booked during this window.</p>
                            <ul class="mt-1 space-y-0.5">
                                @foreach ($conflicts as $c)
                                    <li class="tabular">
                                        <a href="{{ route('releases.show', $c) }}" class="font-medium underline decoration-warning-400 underline-offset-2 hover:decoration-warning-700">{{ $c->name }}</a>
                                        <span class="text-warning-800">({{ $c->start_date->format('M j') }} – {{ $c->end_date->format('M j, Y') }})</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <section class="card overflow-hidden" aria-labelledby="timeline-title">
                    <div class="card-header flex-wrap">
                        <div class="flex items-center gap-2.5">
                            <h2 id="timeline-title" class="card-title flex items-center gap-2">
                                <x-icon name="chart-bar" variant="outline" class="h-4 w-4 text-slate-400" />Timeline
                            </h2>
                            @if ($currentPhase)
                                <span class="badge badge-slate">
                                    <span class="h-1.5 w-1.5 rounded-full" style="background-color: {{ Release::PHASE_COLORS[$currentPhase->phase] ?? '' }}"></span>
                                    {{ $currentPhase->label() }} · phase {{ $phasesDone + 1 }} of {{ $release->phases->count() }}
                                </span>
                            @endif
                        </div>
                        <p class="tabular text-xs text-slate-500">
                            {{ $window }}
                            <span class="mx-1 text-slate-300">·</span>{{ $days($release->durationInDays()) }}
                            <span class="mx-1 text-slate-300">·</span><span class="font-medium text-slate-700">{{ $release->workingDays() }} working</span>
                            @if ($offDays)<span class="mx-1 text-slate-300">·</span>{{ $offDays }} off @endif
                        </p>
                    </div>

                    <div class="space-y-4 px-5 py-4 sm:px-6">
                        {{-- Phase bar, with off-days shaded and today marked --}}
                        <div class="relative h-9 w-full overflow-hidden rounded-lg bg-slate-100 ring-1 ring-inset ring-slate-200/70">
                            @foreach ($release->phases as $phase)
                                @php
                                    $offset = $release->start_date->diffInDays($phase->start_date) / $total * 100;
                                    $width = ($phase->start_date->diffInDays($phase->end_date) + 1) / $total * 100;
                                @endphp
                                <div class="absolute top-0 flex h-9 items-center justify-center overflow-hidden text-[11px] font-medium text-white/95"
                                     style="left: {{ $offset }}%; width: {{ $width }}%; background-color: {{ Release::PHASE_COLORS[$phase->phase] ?? '' }}"
                                     title="{{ $phase->label() }}: {{ $phase->start_date->format('M j') }} – {{ $phase->end_date->format('M j') }}">
                                    <span class="truncate px-1.5">{{ $phase->label() }}</span>
                                </div>
                            @endforeach
                            @foreach ($release->offDays as $off)
                                @php $o = $release->start_date->diffInDays($off->date) / $total * 100; @endphp
                                <div class="absolute top-0 z-10 h-9 bg-slate-900/25"
                                     style="left: {{ $o }}%; width: {{ 1 / $total * 100 }}%"
                                     title="Off: {{ $off->date->format('M j, Y') }}{{ $off->reason ? ' — '.$off->reason : '' }}"></div>
                            @endforeach
                            @if ($todayOffset !== null)
                                <div class="absolute -top-0.5 bottom-[-0.125rem] z-20 w-px bg-slate-900" style="left: {{ $todayOffset }}%" title="Today — {{ $today->format('M j, Y') }}">
                                    <span class="absolute -left-1 -top-1 h-2 w-2 rounded-full bg-slate-900 ring-2 ring-white"></span>
                                </div>
                            @endif
                        </div>

                        {{-- Phase progress: done / current / next, with dates --}}
                        <ol class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach ($release->phases as $phase)
                                @php $ps = $phaseState($phase); @endphp
                                <li @class([
                                        'flex min-w-0 items-start gap-2 rounded-lg px-2.5 py-2 ring-1 ring-inset',
                                        'bg-brand-50/60 ring-brand-200' => $ps === 'current',
                                        'ring-slate-200/70' => $ps !== 'current',
                                    ])
                                    @if ($ps === 'current') aria-current="step" @endif>
                                    @if ($ps === 'done')
                                        <span class="mt-0.5 flex h-4 w-4 flex-none items-center justify-center rounded-full bg-success-100 text-success-600">
                                            <x-icon name="check" variant="outline" class="h-3 w-3" />
                                        </span>
                                    @else
                                        <span class="mt-1 h-2.5 w-2.5 flex-none rounded-full" style="background-color: {{ Release::PHASE_COLORS[$phase->phase] ?? '' }}"></span>
                                    @endif
                                    <span class="min-w-0">
                                        <span @class(['block truncate text-xs font-semibold', 'text-brand-800' => $ps === 'current', 'text-slate-700' => $ps !== 'current'])>
                                            {{ $phase->label() }}@if ($ps === 'current')<span class="font-normal text-brand-700"> · now</span>@endif
                                        </span>
                                        <span class="tabular block truncate text-[11px] text-slate-500">{{ $phase->start_date->format('M j') }} – {{ $phase->end_date->format('M j') }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ol>

                        {{-- Time elapsed vs tasks done: the schedule-health read --}}
                        <div class="grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-2">
                            <div>
                                <div class="mb-1.5 flex items-baseline justify-between gap-2 text-xs">
                                    <span class="font-medium text-slate-600">Time elapsed</span>
                                    <span class="tabular text-slate-500">{{ $elapsed }}% · {{ $windowNote }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100" role="img" aria-label="{{ $elapsed }}% of the window elapsed">
                                    <div @class(['h-full rounded-full', 'bg-success-500' => $state === 'completed', 'bg-warning-500' => $state === 'overdue', 'bg-slate-400' => ! in_array($state, ['completed', 'overdue'], true)])
                                         style="width: {{ $elapsed }}%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="mb-1.5 flex items-baseline justify-between gap-2 text-xs">
                                    <span class="font-medium text-slate-600">Tasks done</span>
                                    <span class="tabular text-slate-500">{{ $taskStats['total'] ? $taskPct.'%' : 'No tasks yet' }}</span>
                                </div>
                                <x-progress :done="$taskStats['done']" :total="$taskStats['total']" size="md" />
                            </div>
                        </div>
                    </div>
                </section>
            @endunless

            {{-- ============ KPI strip ============ --}}
            <div @class([
                'stat-strip',
                'lg:grid-cols-5 max-lg:[&>*:nth-child(-n+4)]:border-b' => ! $limited,
            ])>
                <x-stat label="Tasks done" :value="$taskStats['done'].' / '.$taskStats['total']"
                        :sub="$taskStats['total'] ? $taskPct.'% complete' : 'No tasks yet'" icon="clipboard-check" tone="success" />
                <x-stat label="Open" :value="$taskStats['open']" sub="still to finish" icon="view-columns" tone="brand"
                        :href="route('board.index', ['release_id' => $release->id])" />
                <x-stat label="Overdue" :value="$taskStats['overdue']" :sub="$taskStats['overdue'] ? 'past their due date' : 'nothing late'"
                        icon="clock" tone="danger" :alert="$taskStats['overdue'] > 0" />
                @unless ($limited)
                    <x-stat label="Working days" :value="$release->workingDays()"
                            :sub="'of '.$days($release->durationInDays()).($offDays ? ' · '.$offDays.' off' : '')" icon="calendar" tone="violet" />
                @endunless
                <x-stat label="Members" :value="$release->members->count()" :sub="Str::plural('person', $release->members->count()).' assigned'"
                        icon="user-group" tone="sky" @class(['max-lg:col-span-2' => ! $limited]) />
            </div>

            {{-- ============ Main column + sidebar ============ --}}
            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] xl:grid-cols-[minmax(0,1fr)_24rem]">

                {{-- ============ MAIN COLUMN ============ --}}
                <div class="min-w-0 space-y-6">
                    @if ($release->description)
                        <section class="card" aria-labelledby="description-title">
                            <div class="card-header">
                                <h2 id="description-title" class="card-title flex items-center gap-2">
                                    <x-icon name="document-text" variant="outline" class="h-4 w-4 text-slate-400" />Description
                                </h2>
                            </div>
                            <p class="whitespace-pre-line px-5 py-4 text-sm leading-6 text-slate-700 sm:px-6">{{ $release->description }}</p>
                        </section>
                    @endif

                    {{-- Tasks: adding, status changes and deletes update this card in place --}}
                    <x-live-region id="release-tasks" class="card">
                        <div class="card-header">
                            <h2 class="card-title flex items-center gap-2">
                                <x-icon name="clipboard-check" variant="outline" class="h-4 w-4 text-slate-400" />Tasks
                                <span class="badge badge-slate tabular">{{ $release->rootTasks->count() }}</span>
                            </h2>
                            <a href="{{ route('board.index', ['release_id' => $release->id]) }}"
                               class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700">
                                <x-icon name="view-columns" variant="outline" class="h-4 w-4" />Board view
                            </a>
                        </div>
                        <div class="p-4 sm:p-6">
                            @include('partials.tasks-panel', ['release' => $release, 'users' => $users])
                        </div>
                    </x-live-region>

                    {{-- Comments: posting, editing and deleting update this card in place --}}
                    <x-live-region id="release-comments" class="card">
                        <div class="card-header">
                            <h2 class="card-title flex items-center gap-2">
                                <x-icon name="chat-bubbles" variant="outline" class="h-4 w-4 text-slate-400" />Comments
                                <span class="badge badge-slate tabular">{{ $release->comments->count() }}</span>
                            </h2>
                        </div>
                        <div class="p-4 sm:p-6">
                            @include('partials.comments', ['comments' => $release->comments, 'storeUrl' => route('releases.comments.store', $release)])
                        </div>
                    </x-live-region>

                    {{-- Meeting notes --}}
                    <section class="card" aria-labelledby="meeting-notes-title">
                        <div class="card-header">
                            <h2 id="meeting-notes-title" class="card-title flex items-center gap-2">
                                <x-icon name="pencil-square" variant="outline" class="h-4 w-4 text-slate-400" />Meeting notes
                                <span class="badge badge-slate tabular">{{ $release->meetingNotes->count() }}</span>
                            </h2>
                            <div class="flex items-center gap-3">
                                @if ($release->meetingNotes->count() > 5)
                                    <a href="{{ route('meeting-notes.index', ['release' => $release->id]) }}"
                                       class="text-xs font-medium text-brand-600 hover:text-brand-700">View all</a>
                                @endif
                                <a href="{{ route('meeting-notes.create', ['release' => $release->id]) }}" class="btn-secondary btn-sm">
                                    <x-icon name="plus" variant="outline" class="h-4 w-4" /><span class="sr-only sm:not-sr-only">New meeting note</span>
                                </a>
                            </div>
                        </div>
                        @if ($release->meetingNotes->isEmpty())
                            <p class="px-5 py-6 text-center text-sm text-slate-400 sm:px-6">No meeting notes for this release yet.</p>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach ($release->meetingNotes->take(5) as $note)
                                    <li>
                                        <a href="{{ route('meeting-notes.show', $note) }}" class="group block px-5 py-3.5 transition-colors hover:bg-slate-50/70 sm:px-6">
                                            <div class="flex items-baseline justify-between gap-3">
                                                <span class="truncate text-sm font-medium text-slate-800 group-hover:text-brand-700">{{ $note->title }}</span>
                                                <span class="tabular shrink-0 text-xs text-slate-400">{{ $note->meeting_date->format('M j, Y') }}</span>
                                            </div>
                                            <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-slate-400">
                                                <x-meeting-note-type :note="$note" />
                                                <span>{{ $note->author->name ?? 'Unknown' }}</span>
                                            </p>
                                            <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ Str::limit(trim(strip_tags($note->body)), 160) }}</p>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    {{-- Links --}}
                    <section class="card" aria-labelledby="links-title">
                        <div class="card-header">
                            <h2 id="links-title" class="card-title flex items-center gap-2">
                                <x-icon name="link" variant="outline" class="h-4 w-4 text-slate-400" />Links
                                <span class="badge badge-slate tabular">{{ $releaseLinks->count() }}</span>
                            </h2>
                        </div>
                        @if ($releaseLinks->isEmpty())
                            <p class="px-5 py-6 text-center text-sm text-slate-400 sm:px-6">No links for this release yet.</p>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach ($releaseLinks as $link)
                                    <li class="flex items-center justify-between gap-3 px-5 py-3 sm:px-6">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <span class="flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                                                <x-icon name="globe" variant="outline" class="h-4 w-4" />
                                            </span>
                                            <div class="min-w-0">
                                                <a href="{{ $link->url }}" target="_blank" rel="noopener"
                                                   class="block truncate text-sm font-medium text-slate-800 hover:text-brand-700">{{ $link->label }}</a>
                                                <p class="mt-0.5 flex items-center gap-1 truncate text-xs text-slate-400">
                                                    {{ $link->author->name ?? 'Unknown' }}
                                                    @if (! $link->isShared())
                                                        · <x-icon name="lock-closed" variant="outline" class="h-3 w-3" />Private
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                        @can('delete', $link)
                                            <form method="POST" action="{{ route('quick-links.destroy', $link) }}" data-confirm="Delete this link?" data-confirm-verb="Delete">
                                                @csrf @method('DELETE')
                                                <button class="rounded-md p-1.5 text-slate-400 transition-colors hover:bg-danger-50 hover:text-danger-600" aria-label="Delete {{ $link->label }}">
                                                    <x-icon name="trash" class="h-3.5 w-3.5" />
                                                </button>
                                            </form>
                                        @endcan
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        {{-- Add a link to this release --}}
                        <form method="POST" action="{{ route('quick-links.store') }}" class="card-footer flex-wrap justify-start">
                            @csrf
                            <input type="hidden" name="release_id" value="{{ $release->id }}">
                            <label for="link-label" class="sr-only">Label</label>
                            <input id="link-label" name="label" type="text" required maxlength="100" placeholder="Label" @invalid('label')
                                   class="field-input field-sm !mt-0 w-full sm:w-40 sm:flex-none">
                            <label for="link-url" class="sr-only">URL</label>
                            <input id="link-url" name="url" type="url" required placeholder="https://…" @invalid('url')
                                   class="field-input field-sm !mt-0 min-w-0 flex-1">
                            <select name="visibility" aria-label="Visibility" class="field-select field-sm !mt-0 w-auto flex-none pr-8">
                                <option value="shared">Shared</option>
                                <option value="private">Private</option>
                            </select>
                            <button class="btn-secondary btn-sm">Add link</button>
                            @foreach (['label', 'url'] as $f)
                                @error($f)
                                    <p id="{{ $f }}-error" class="field-error !mt-0 w-full">
                                        <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}
                                    </p>
                                @enderror
                            @endforeach
                        </form>
                    </section>
                </div>

                {{-- ============ SIDEBAR ============ --}}
                <aside class="min-w-0 space-y-6">

                    {{-- Claude: risk brief, release notes, catch-up, document insights --}}
                    @include('ai.panels.release', ['release' => $release])

                    {{-- Details (planning metadata — hidden from developers/QA) --}}
                    @unless ($limited)
                        @php $row = 'flex items-center justify-between gap-3 px-5 py-2.5 sm:px-6'; @endphp
                        <section class="card" aria-labelledby="details-title">
                            <div class="card-header">
                                <h2 id="details-title" class="card-title flex items-center gap-2">
                                    <x-icon name="identification" variant="outline" class="h-4 w-4 text-slate-400" />Details
                                </h2>
                                <span class="tabular text-xs font-medium text-slate-400">#{{ $release->id }}</span>
                            </div>
                            <dl class="divide-y divide-slate-100 text-sm">
                                <div class="{{ $row }}">
                                    <dt class="text-slate-500">Status</dt>
                                    <dd><x-release-state :release="$release" /></dd>
                                </div>
                                <div class="{{ $row }}">
                                    <dt class="text-slate-500">Project</dt>
                                    <dd class="min-w-0 font-medium text-slate-800">
                                        <x-color-chip :color="$release->project->color" :label="$release->project->name" :href="route('projects.show', $release->project)" class="max-w-full" />
                                    </dd>
                                </div>
                                <div class="{{ $row }}">
                                    <dt class="text-slate-500">Team</dt>
                                    <dd class="min-w-0 font-medium text-slate-800">
                                        <x-color-chip :color="$release->team->color" :label="$release->team->name" :href="route('teams.show', $release->team)" class="max-w-full" />
                                    </dd>
                                </div>
                                <div class="{{ $row }}">
                                    <dt class="text-slate-500">Quarter</dt>
                                    <dd class="tabular font-medium text-slate-800">{{ $release->year }} · {{ $release->quarterLabel() }}</dd>
                                </div>
                                <div class="{{ $row }}">
                                    <dt class="text-slate-500">Window</dt>
                                    <dd class="tabular text-right font-medium text-slate-800">{{ $window }}</dd>
                                </div>
                                <div class="{{ $row }}">
                                    <dt class="text-slate-500">Working days</dt>
                                    <dd class="tabular font-medium text-slate-800">{{ $release->workingDays() }}<span class="font-normal text-slate-400"> / {{ $release->durationInDays() }}</span></dd>
                                </div>
                            </dl>
                        </section>
                    @endunless

                    {{-- Members --}}
                    <section class="card" aria-labelledby="members-title">
                        <div class="card-header">
                            <h2 id="members-title" class="card-title flex items-center gap-2">
                                <x-icon name="user-group" variant="outline" class="h-4 w-4 text-slate-400" />Members
                                <span class="badge badge-slate tabular">{{ $release->members->count() }}</span>
                            </h2>
                            @if ($canManage)
                                <a href="{{ route('releases.edit', $release) }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">Edit</a>
                            @endif
                        </div>
                        @if ($release->members->isEmpty())
                            <p class="px-5 py-6 text-center text-sm text-slate-400">No members assigned.</p>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach ($release->members as $member)
                                    <li class="flex items-center gap-3 px-5 py-2.5 sm:px-6">
                                        <x-avatar :user="$member" />
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-slate-800">{{ $member->name }}</p>
                                            <p class="truncate text-xs text-slate-400">{{ $member->roleLabel() }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    {{-- Completion --}}
                    @if ($release->isComplete())
                        <section class="card overflow-hidden" aria-labelledby="completion-title">
                            <div class="flex items-center gap-3 border-b border-slate-100 bg-success-50/60 px-5 py-4 sm:px-6">
                                <span class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-success-100 text-success-600">
                                    <x-icon name="check" variant="outline" class="h-5 w-5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <h2 id="completion-title" class="card-title">Completed</h2>
                                    <p class="card-subtitle truncate">
                                        {{ $release->completed_at->format('M j, Y') }}
                                        @if ($release->completedBy) · by {{ $release->completedBy->name }} @endif
                                    </p>
                                </div>
                                @if ($canManage)
                                    <form method="POST" action="{{ route('releases.reopen', $release) }}">
                                        @csrf
                                        <button class="btn-secondary btn-sm">Reopen</button>
                                    </form>
                                @endif
                            </div>
                            @if ($release->renderedCompletionNotes())
                                <div class="prose-notes px-5 py-4 text-sm text-slate-700 sm:px-6">{!! $release->renderedCompletionNotes() !!}</div>
                            @endif
                        </section>
                    @elseif ($canManage)
                        <section class="card card-pad" x-data="{ open: false }" aria-labelledby="complete-title">
                            <h2 id="complete-title" class="card-title">Mark complete</h2>
                            <p class="card-subtitle">Drops it off the dashboard and stops it counting against the team's schedule. Reopen anytime.</p>
                            <button type="button" @click="open = !open" x-show="!open" class="btn-primary btn-sm mt-3 w-full">
                                <x-icon name="check" variant="outline" class="h-4 w-4" />Mark complete
                            </button>
                            <form x-show="open" x-cloak method="POST" action="{{ route('releases.complete', $release) }}" class="mt-3">
                                @csrf
                                <label for="completion_notes" class="field-label">Notes<span class="field-optional">Optional</span></label>
                                <textarea id="completion_notes" name="completion_notes" rows="4" class="field-textarea" aria-describedby="completion_notes-hint" placeholder="What shipped, known issues, follow-ups…"></textarea>
                                <p id="completion_notes-hint" class="field-hint">Markdown is supported.</p>
                                <div class="mt-3 flex items-center gap-2">
                                    <button class="btn-primary btn-sm">Confirm</button>
                                    <button type="button" @click="open = false" class="btn-ghost btn-sm">Cancel</button>
                                </div>
                            </form>
                        </section>
                    @endif

                    {{-- Off-days (planning surface — hidden from developers/QA) --}}
                    @unless ($limited)
                        <section class="card" aria-labelledby="offdays-title">
                            <div class="card-header">
                                <h2 id="offdays-title" class="card-title flex items-center gap-2">
                                    <x-icon name="calendar" variant="outline" class="h-4 w-4 text-slate-400" />Off-days
                                    <span class="badge badge-slate tabular">{{ $offDays }}</span>
                                </h2>
                                @if ($canManage)
                                    <form method="POST" action="{{ route('releases.offdays.weekends', $release) }}">
                                        @csrf
                                        <button class="text-xs font-medium text-brand-600 hover:text-brand-700">Mark weekends</button>
                                    </form>
                                @endif
                            </div>

                            @if ($canManage)
                                <form method="POST" action="{{ route('releases.offdays.store', $release) }}" class="grid gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
                                    @csrf
                                    <div>
                                        <label for="offday-date" class="field-label">Date</label>
                                        <input id="offday-date" type="date" name="date" required min="{{ $release->start_date->toDateString() }}" max="{{ $release->end_date->toDateString() }}" @invalid('date') class="field-input">
                                        @error('date')
                                            <p id="date-error" class="field-error"><x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="offday-reason" class="field-label">Reason<span class="field-optional">Optional</span></label>
                                        <input id="offday-reason" type="text" name="reason" placeholder="Holiday, off-site…" class="field-input">
                                    </div>
                                    <button class="btn-secondary btn-sm w-full">
                                        <x-icon name="plus" variant="outline" class="h-4 w-4" />Mark off-day
                                    </button>
                                </form>
                            @endif

                            @if ($release->offDays->isEmpty())
                                <p class="px-5 py-6 text-center text-sm text-slate-400">No off-days marked.</p>
                            @else
                                <ul class="flex flex-wrap gap-2 px-5 py-4 sm:px-6">
                                    @foreach ($release->offDays as $off)
                                        <li class="inline-flex max-w-full items-center gap-1.5 rounded-full bg-slate-100 py-1 pl-3 pr-1.5 text-xs text-slate-700 ring-1 ring-inset ring-slate-500/10">
                                            <span class="tabular font-medium">{{ $off->date->format('D, M j') }}</span>
                                            @if ($off->reason)<span class="truncate text-slate-500">· {{ $off->reason }}</span>@endif
                                            @if ($canManage)
                                                <form method="POST" action="{{ route('releases.offdays.destroy', [$release, $off]) }}">
                                                    @csrf @method('DELETE')
                                                    <button class="flex h-4 w-4 items-center justify-center rounded-full text-slate-400 hover:bg-danger-50 hover:text-danger-600" title="Remove" aria-label="Remove {{ $off->date->format('M j') }}">
                                                        <x-icon name="x-mark" variant="outline" class="h-3 w-3" />
                                                    </button>
                                                </form>
                                            @else
                                                <span class="w-1"></span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </section>
                    @endunless

                    {{-- Documents --}}
                    <section class="card" aria-labelledby="documents-title">
                        <div class="card-header">
                            <h2 id="documents-title" class="card-title flex items-center gap-2">
                                <x-icon name="document-text" variant="outline" class="h-4 w-4 text-slate-400" />Documents
                                <span class="badge badge-slate tabular">{{ $release->documents->count() }}</span>
                            </h2>
                        </div>

                        @unless ($me->isViewer())
                            <form method="POST" action="{{ route('releases.documents.store', $release) }}" enctype="multipart/form-data" class="space-y-2 border-b border-slate-100 px-5 py-4 sm:px-6">
                                @csrf
                                <label for="release-document" class="sr-only">Document</label>
                                <input id="release-document" type="file" name="document" required @invalid('document')
                                       class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs text-slate-400">Max 20 MB</span>
                                    <button class="btn-primary btn-sm">Upload</button>
                                </div>
                                @error('document')
                                    <p id="document-error" class="field-error"><x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}</p>
                                @enderror
                            </form>
                        @endunless

                        @if ($release->documents->isEmpty())
                            <p class="px-5 py-6 text-center text-sm text-slate-400">No documents attached.</p>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach ($release->documents as $document)
                                    <li class="flex items-center justify-between gap-2 px-5 py-3 sm:px-6">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <span class="flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                                                <x-icon name="document-text" variant="outline" class="h-4 w-4" />
                                            </span>
                                            <div class="min-w-0">
                                                <a href="{{ route('releases.documents.download', [$release, $document]) }}" data-turbo="false" class="block truncate text-sm font-medium text-slate-800 hover:text-brand-600">{{ $document->original_name }}</a>
                                                <p class="tabular text-xs text-slate-400">{{ $document->humanSize() }} · {{ $document->created_at->format('M j, Y') }}</p>
                                            </div>
                                        </div>
                                        @if ($canManage)
                                            <form method="POST" action="{{ route('releases.documents.destroy', [$release, $document]) }}"
                                                  data-confirm="Delete this document?" data-confirm-verb="Delete">
                                                @csrf @method('DELETE')
                                                <button class="rounded-md p-1.5 text-slate-400 transition-colors hover:bg-danger-50 hover:text-danger-600" aria-label="Delete {{ $document->original_name }}">
                                                    <x-icon name="trash" class="h-3.5 w-3.5" />
                                                </button>
                                            </form>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    {{-- History (activity is a planning surface — hidden from developers/QA) --}}
                    @unless ($limited)
                        <section class="card" x-data="{ open: false }">
                            <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-controls="release-history"
                                    class="card-header w-full border-b-0 text-left">
                                <h2 class="card-title flex items-center gap-2">
                                    <x-icon name="activity" class="h-4 w-4 text-slate-400" />History
                                </h2>
                                <span class="flex items-center gap-2 text-xs text-slate-400">
                                    <span class="tabular">{{ $history->count() }}</span>
                                    <x-icon name="chevron-down" class="h-4 w-4 transition-transform" ::class="open && 'rotate-180'" />
                                </span>
                            </button>
                            <div id="release-history" x-show="open" x-cloak class="max-h-96 overflow-y-auto border-t border-slate-100 px-4">
                                @include('partials.activity-list', ['activities' => $history])
                            </div>
                        </section>
                    @endunless
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
