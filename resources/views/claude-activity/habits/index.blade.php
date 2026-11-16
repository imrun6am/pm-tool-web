@php
    use App\Models\ClaudeHabitReport;

    $statusBadge = [
        ClaudeHabitReport::STATUS_QUEUED => ['badge-slate', 'Waiting'],
        ClaudeHabitReport::STATUS_PROCESSING => ['badge-info', 'Writing…'],
        ClaudeHabitReport::STATUS_COMPLETED => ['badge-success', 'Ready'],
        ClaudeHabitReport::STATUS_FAILED => ['badge-danger', 'Failed'],
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Claude Code habit reports"
                       description="An AI review of one person's Claude Code prompts and sessions: what they ask for, which projects, good practice, mistakes and what to improve. It covers the devices linked to them on the Claude activity page."
                       :breadcrumbs="['Claude activity' => route('claude-activity.index'), 'Habit reports' => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-6">
            @unless ($available)
                <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="status">
                    <x-icon name="exclamation-circle" variant="outline" class="mt-0.5 h-5 w-5 flex-none" />
                    <p>AI isn't available for habit reports: it's switched off on the AI usage page, or no API key is set. Reports asked for now will fail.</p>
                </div>
            @endunless

            <section class="card" aria-labelledby="habit-new">
                <div class="card-header">
                    <h2 id="habit-new" class="card-title">New report</h2>
                </div>
                @if ($people->isEmpty())
                    <x-empty-state icon="user-group" title="No one to review yet"
                                   description="On the Claude activity page, link each device to the person who uses it. Their reports cover all their linked devices." class="p-6" />
                @else
                    <form method="POST" action="{{ route('claude-activity.habits.store') }}" class="flex flex-wrap items-end gap-3 p-4 sm:p-6">
                        @csrf
                        <div>
                            <label for="habit-user" class="field-label">Person</label>
                            <select id="habit-user" name="user_id" class="field-select mt-1 min-w-[12rem]" required>
                                @foreach ($people as $person)
                                    <option value="{{ $person->id }}" @selected((int) old('user_id') === $person->id)>{{ $person->name }} ({{ $person->claude_devices_count }} {{ \Illuminate\Support\Str::plural('device', $person->claude_devices_count) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="habit-from" class="field-label">From</label>
                            <input id="habit-from" type="date" name="from" value="{{ old('from', today()->subDays(29)->toDateString()) }}" max="{{ today()->toDateString() }}" class="field-input mt-1" required>
                        </div>
                        <div>
                            <label for="habit-to" class="field-label">To</label>
                            <input id="habit-to" type="date" name="to" value="{{ old('to', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="field-input mt-1" required>
                        </div>
                        <button class="btn-primary">
                            <x-icon name="sparkles" variant="outline" class="h-4 w-4" />Write report
                        </button>
                        @if ($errors->any())
                            <div class="w-full space-y-1">
                                @foreach ($errors->all() as $message)
                                    <p class="field-error" role="alert">{{ $message }}</p>
                                @endforeach
                            </div>
                        @endif
                        <p class="w-full text-xs text-slate-500">It's written in the background and takes about a minute. The AI sees the stored start of each prompt and the session details, never Claude's answers.</p>
                    </form>
                @endif
            </section>

            <x-poll-region id="habit-reports" :running="$running" done-message="Habit report ready.">
                <section class="card" aria-labelledby="habit-history">
                    <div class="card-header">
                        <h2 id="habit-history" class="card-title">Reports</h2>
                    </div>
                    @if ($reports->isEmpty())
                        <x-empty-state icon="document-text" title="No reports yet" class="p-6" />
                    @else
                        <div class="table-wrap rounded-none ring-0">
                            <table class="table-base">
                                <thead>
                                    <tr>
                                        <th scope="col">Person</th>
                                        <th scope="col">Period</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Asked by</th>
                                        <th scope="col"><span class="sr-only">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($reports as $report)
                                        @php [$tone, $label] = $statusBadge[$report->status] ?? ['badge-slate', $report->status]; @endphp
                                        <tr>
                                            <td class="font-medium text-slate-900">
                                                @if ($report->status === ClaudeHabitReport::STATUS_COMPLETED)
                                                    <a href="{{ route('claude-activity.habits.show', $report) }}" class="hover:text-brand-700 hover:underline">{{ $report->user?->name ?? 'Deleted user' }}</a>
                                                @else
                                                    {{ $report->user?->name ?? 'Deleted user' }}
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap text-slate-600">{{ $report->period_start->format('M j, Y') }} – {{ $report->period_end->format('M j, Y') }}</td>
                                            <td>
                                                <span class="badge {{ $tone }}">{{ $label }}</span>
                                                @if ($report->error)
                                                    <p class="mt-1 max-w-xs text-xs text-danger-600">{{ $report->error }}</p>
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap text-sm text-slate-600">
                                                {{ $report->requester?->name ?? '—' }}
                                                <span class="block text-xs text-slate-500">{{ $report->created_at->diffForHumans() }}</span>
                                            </td>
                                            <td class="whitespace-nowrap text-right">
                                                <div class="flex items-center justify-end gap-1">
                                                    @if ($report->status === ClaudeHabitReport::STATUS_COMPLETED)
                                                        <a href="{{ route('claude-activity.habits.show', $report) }}" class="btn-secondary btn-sm">Open</a>
                                                    @endif
                                                    <form method="POST" action="{{ route('claude-activity.habits.destroy', $report) }}"
                                                          data-confirm-title="Delete this report?" data-confirm-verb="Delete"
                                                          data-confirm="The habit report for {{ $report->user?->name ?? 'this person' }} will be deleted. The activity it was written from stays.">
                                                        @csrf @method('DELETE')
                                                        <button class="btn-ghost btn-sm text-slate-400 hover:text-danger-600" aria-label="Delete this report">
                                                            <x-icon name="trash" class="h-4 w-4" />
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="border-t border-slate-100 px-4 py-3 sm:px-6">{{ $reports->links() }}</div>
                    @endif
                </section>
            </x-poll-region>
        </div>
    </div>
</x-app-layout>
