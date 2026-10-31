@php
    $conflicts = $conflicts ?? [];
    $showProject = $showProject ?? true;
    $showTeam = $showTeam ?? true;
@endphp

@if ($releases->isEmpty())
    <div class="p-10 text-center text-slate-500">No releases yet.</div>
@else
    <table class="table-base">
        <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-6 py-3">Release</th>
                @if ($showProject)<th class="px-6 py-3">Project</th>@endif
                @if ($showTeam)<th class="px-6 py-3">Team</th>@endif
                <th class="px-6 py-3">Quarter</th>
                <th class="px-6 py-3">Window</th>
                <th class="px-6 py-3">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($releases as $release)
                <tr class="{{ ($conflicts[$release->id] ?? false) ? 'bg-amber-50/60' : '' }}">
                    <td class="px-6 py-4">
                        <a href="{{ route('releases.show', $release) }}" class="font-medium text-slate-900 hover:text-indigo-600">{{ $release->name }}</a>
                    </td>
                    @if ($showProject)
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $release->project->color }}"></span>
                                {{ $release->project->name }}
                            </span>
                        </td>
                    @endif
                    @if ($showTeam)
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $release->team->color }}"></span>
                                {{ $release->team->name }}
                            </span>
                        </td>
                    @endif
                    <td class="px-6 py-4 text-slate-600">{{ $release->year }} · {{ $release->quarterLabel() }}</td>
                    <td class="px-6 py-4 text-slate-600">{{ $release->start_date->format('M j') }} – {{ $release->end_date->format('M j, Y') }}</td>
                    <td class="px-6 py-4">
                        @if ($conflicts[$release->id] ?? false)
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">
                                <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.515 2.625H3.72c-1.344 0-2.187-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>
                                Overlap
                            </span>
                        @else
                            <span class="text-xs text-slate-400">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
