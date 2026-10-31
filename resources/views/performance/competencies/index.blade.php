<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('performance.index') }}" class="btn-secondary btn-sm !px-2" aria-label="Back">‹</a>
                <div>
                    <h2 class="page-title">Competencies</h2>
                    <p class="text-xs text-slate-400">The scoring framework · applied to every team</p>
                </div>
            </div>
            <a href="{{ route('performance.competencies.create') }}" class="btn-primary btn-sm">
                <x-icon name="plus" class="h-4 w-4" /> New competency
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <div class="card overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Competency</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Category</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Applies to</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Cadence</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Weight</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($competencies as $competency)
                            <tr class="{{ $competency->active ? '' : 'bg-slate-50/60' }}">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-800">{{ $competency->name }}</div>
                                    @if ($competency->description)<div class="max-w-md text-xs text-slate-400">{{ $competency->description }}</div>@endif
                                    @if ($competency->scores_count)<div class="mt-0.5 text-[11px] text-slate-400">{{ $competency->scores_count }} {{ \Illuminate\Support\Str::plural('rating', $competency->scores_count) }} recorded</div>@endif
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $competency->categoryLabel() }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $competency->roleScopeLabel() }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $competency->cadenceLabel() }}</td>
                                <td class="px-4 py-3 text-center font-semibold text-slate-700 tabular">×{{ $competency->weight }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if ($competency->active)
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">Active</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('performance.competencies.edit', $competency) }}" class="btn-ghost btn-sm !px-2" aria-label="Edit"><x-icon name="pencil" class="h-4 w-4" /></a>
                                        <form method="POST" action="{{ route('performance.competencies.toggle', $competency) }}">
                                            @csrf
                                            <button class="btn-ghost btn-sm">{{ $competency->active ? 'Deactivate' : 'Activate' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('performance.competencies.destroy', $competency) }}"
                                              onsubmit="return confirm('Delete this competency? This cannot be undone.')">
                                            @csrf @method('DELETE')
                                            <button class="btn-ghost btn-sm !px-2 text-rose-600 hover:bg-rose-50" aria-label="Delete"><x-icon name="trash" class="h-4 w-4" /></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-slate-500">No competencies yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-slate-400">A competency with recorded ratings can’t be deleted — deactivate it to retire it while keeping its history.</p>
        </div>
    </div>
</x-app-layout>
