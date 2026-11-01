@php
    /*
     * A list of releases: a table from md up, stacked cards on phones.
     *   @include('partials.releases-table', ['releases' => $releases, 'conflicts' => $conflicts,
     *            'showProject' => true, 'showTeam' => false])
     * conflicts: OverlapChecker::flagConflicts() flags, keyed by release id.
     * Task progress shows when the releases carry Release::withTaskProgress()
     * counts; the member stack when their `members` relation is loaded.
     */
    $conflicts = $conflicts ?? [];
    $showProject = $showProject ?? true;
    $showTeam = $showTeam ?? true;

    $first = $releases->first();
    $showProgress = $first && array_key_exists('tasks_total', $first->getAttributes());
    $showMembers = $first && $first->relationLoaded('members');

    $range = fn ($r) => $r->start_date->year === $r->end_date->year
        ? $r->start_date->format('M j').' – '.$r->end_date->format('M j, Y')
        : $r->start_date->format('M j, Y').' – '.$r->end_date->format('M j, Y');
@endphp

@if ($releases->isEmpty())
    <x-empty-state icon="rocket" title="No releases yet." />
@else
    {{-- md and up: table (scrolls inside its card when narrow) --}}
    <div class="table-wrap hidden md:block">
        <table class="table-base">
            <thead>
                <tr>
                    <th scope="col">Release</th>
                    @if ($showProject || $showTeam)<th scope="col">{{ $showProject && $showTeam ? 'Project · Team' : ($showProject ? 'Project' : 'Team') }}</th>@endif
                    <th scope="col">Window</th>
                    @if ($showProgress)<th scope="col">Progress</th>@endif
                    @if ($showMembers)<th scope="col">Members</th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach ($releases as $release)
                    @php
                        $conflict = $conflicts[$release->id] ?? false;
                        $p = $showProgress ? $release->taskProgress() : null;
                    @endphp
                    <tr @class(['bg-warning-50/40' => $conflict])>
                        <td class="max-w-[20rem]">
                            <a href="{{ route('releases.show', $release) }}"
                               class="block truncate font-medium text-slate-900 hover:text-brand-700" title="{{ $release->name }}">{{ $release->name }}</a>
                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                <x-release-state :release="$release" />
                                @if ($conflict)
                                    <span class="badge badge-warning" title="Team {{ $release->team->name }} is double-booked during this window">
                                        <x-icon name="exclamation-circle" variant="outline" class="h-3.5 w-3.5" />Overlap
                                    </span>
                                @endif
                            </div>
                        </td>
                        @if ($showProject || $showTeam)
                            <td>
                                <div class="flex flex-col gap-1 text-slate-600">
                                    @if ($showProject)
                                        <x-color-chip :color="$release->project->color" :label="$release->project->name" :href="route('projects.show', $release->project)" />
                                    @endif
                                    @if ($showTeam)
                                        <x-color-chip :color="$release->team->color" :label="$release->team->name" :href="route('teams.show', $release->team)" />
                                    @endif
                                </div>
                            </td>
                        @endif
                        <td class="whitespace-nowrap">
                            <p class="tabular text-slate-700">{{ $range($release) }}</p>
                            <p class="tabular mt-0.5 text-xs text-slate-400">
                                {{ $release->durationInDays() }} {{ \Illuminate\Support\Str::plural('day', $release->durationInDays()) }}
                                · {{ $release->year }} {{ $release->quarterLabel() }}
                            </p>
                        </td>
                        @if ($showProgress)
                            <td class="w-48 min-w-[11rem]">
                                <x-progress :done="$p['done']" :total="$p['total']" />
                                <p class="tabular mt-1 text-xs text-slate-400">
                                    @if ($release->isComplete())
                                        Shipped {{ $release->completed_at->format('M j, Y') }}
                                    @elseif ($release->state() === 'active')
                                        {{ $release->elapsedPercent() }}% of window elapsed
                                    @elseif ($release->state() === 'upcoming')
                                        @php $days = (int) round(today()->diffInDays($release->start_date, true)); @endphp
                                        Starts in {{ $days }} {{ \Illuminate\Support\Str::plural('day', $days) }}
                                    @else
                                        @php $days = (int) round(today()->diffInDays($release->end_date, true)); @endphp
                                        Window ended {{ $days }} {{ \Illuminate\Support\Str::plural('day', $days) }} ago
                                    @endif
                                </p>
                            </td>
                        @endif
                        @if ($showMembers)
                            <td class="whitespace-nowrap">
                                @include('releases.partials.member-stack', ['members' => $release->members])
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Phones: one card per release --}}
    <ul class="divide-y divide-slate-100 md:hidden">
        @foreach ($releases as $release)
            @php
                $conflict = $conflicts[$release->id] ?? false;
                $p = $showProgress ? $release->taskProgress() : null;
            @endphp
            <li @class(['px-4 py-4', 'bg-warning-50/40' => $conflict])>
                <div class="flex items-start justify-between gap-3">
                    <a href="{{ route('releases.show', $release) }}" class="min-w-0 font-medium text-slate-900 hover:text-brand-700">
                        <span class="line-clamp-2">{{ $release->name }}</span>
                    </a>
                    <x-release-state :release="$release" class="flex-none" />
                </div>
                @if ($showProject || $showTeam)
                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-600">
                        @if ($showProject)
                            <x-color-chip :color="$release->project->color" :label="$release->project->name" class="max-w-full" />
                        @endif
                        @if ($showTeam)
                            <x-color-chip :color="$release->team->color" :label="$release->team->name" class="max-w-full" />
                        @endif
                    </div>
                @endif
                <p class="tabular mt-2 flex flex-wrap items-center gap-x-1.5 text-xs text-slate-500">
                    <x-icon name="calendar" variant="outline" class="h-3.5 w-3.5 text-slate-400" />
                    {{ $range($release) }}
                    <span class="text-slate-300">·</span>{{ $release->durationInDays() }} {{ \Illuminate\Support\Str::plural('day', $release->durationInDays()) }}
                </p>
                @if ($showProgress || $showMembers || $conflict)
                    <div class="mt-3 flex items-center gap-3">
                        @if ($showProgress)
                            <x-progress :done="$p['done']" :total="$p['total']" class="flex-1" />
                        @endif
                        @if ($showMembers)
                            @include('releases.partials.member-stack', ['members' => $release->members, 'max' => 3])
                        @endif
                    </div>
                    @if ($conflict)
                        <p class="mt-2">
                            <span class="badge badge-warning">
                                <x-icon name="exclamation-circle" variant="outline" class="h-3.5 w-3.5" />Overlap · team double-booked
                            </span>
                        </p>
                    @endif
                @endif
            </li>
        @endforeach
    </ul>
@endif
