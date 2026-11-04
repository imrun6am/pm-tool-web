@php
    use App\Models\RecurringTask;
    use App\Models\Task;
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Recurring tasks" description="Work that comes round again. Each run creates a normal task." :breadcrumbs="['All tasks' => route('tasks.index'), 'Recurring tasks' => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <x-live-region id="recurring" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
                <div class="list-card min-w-0">
                    @forelse ($rules as $rule)
                        <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p @class(['break-words text-sm font-medium', 'text-slate-900' => $rule->is_active, 'text-slate-400' => ! $rule->is_active])>{{ $rule->title }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ RecurringTask::FREQUENCIES[$rule->frequency] ?? $rule->frequency }}
                                    @if ($rule->is_active) · next {{ $rule->next_run_on->format('M j, Y') }} @else · paused @endif
                                    · {{ $rule->assignee?->name ?? 'Unassigned' }}
                                    @if ($rule->team) · {{ $rule->team->name }} @endif
                                </p>
                            </div>
                            <form method="POST" action="{{ route('recurring-tasks.update', $rule) }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="is_active" value="{{ $rule->is_active ? 0 : 1 }}">
                                <button class="btn-secondary btn-sm">{{ $rule->is_active ? 'Pause' : 'Resume' }}</button>
                            </form>
                            <form method="POST" action="{{ route('recurring-tasks.destroy', $rule) }}" data-confirm="Remove “{{ Str::limit($rule->title, 80) }}”? Tasks it already made stay." data-confirm-verb="Remove">
                                @csrf @method('DELETE')
                                <button class="btn-ghost btn-sm btn-icon h-7 w-7 text-slate-400 hover:text-danger-600" aria-label="Remove {{ $rule->title }}">
                                    <x-icon name="x-mark" variant="outline" class="h-4 w-4" />
                                </button>
                            </form>
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-sm text-slate-500">Nothing repeats yet.</p>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('recurring-tasks.store') }}" class="card space-y-3 p-4">
                    @csrf
                    <h2 class="card-title">Add a recurring task</h2>
                    <div>
                        <label for="rule-title" class="field-label !mt-0">Title</label>
                        <input id="rule-title" name="title" required maxlength="255" value="{{ old('title') }}" class="field-control" autocomplete="off">
                        @error('title')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="rule-frequency" class="field-label !mt-0">Repeats</label>
                            <select id="rule-frequency" name="frequency" class="field-select field-sm !mt-1">
                                @foreach (RecurringTask::FREQUENCIES as $value => $label)
                                    <option value="{{ $value }}" @selected(old('frequency') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="rule-next" class="field-label !mt-0">First run</label>
                            <input id="rule-next" type="date" name="next_run_on" required min="{{ today()->toDateString() }}" value="{{ old('next_run_on', today()->toDateString()) }}" class="field-input field-sm !mt-1">
                        </div>
                        @error('next_run_on')<p class="field-error col-span-2" role="alert">{{ $message }}</p>@enderror
                        <div>
                            <label for="rule-team" class="field-label !mt-0">Team</label>
                            <select id="rule-team" name="team_id" class="field-select field-sm !mt-1">
                                <option value="">No team</option>
                                @foreach ($teams as $t)
                                    <option value="{{ $t->id }}" @selected((int) old('team_id') === $t->id)>{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="rule-assignee" class="field-label !mt-0">Assignee</label>
                            <select id="rule-assignee" name="assignee_id" class="field-select field-sm !mt-1">
                                <option value="">Unassigned</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}" @selected((int) old('assignee_id') === $u->id)>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="rule-priority" class="field-label !mt-0">Priority</label>
                            <select id="rule-priority" name="priority" class="field-select field-sm !mt-1">
                                <option value="">No priority</option>
                                @foreach (Task::PRIORITIES as $value => $label)
                                    <option value="{{ $value }}" @selected(old('priority') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="rule-estimate" class="field-label !mt-0">Estimate</label>
                            <input id="rule-estimate" name="estimate" placeholder="1h 30m" value="{{ old('estimate') }}" class="field-input field-sm !mt-1" autocomplete="off">
                        </div>
                        @error('estimate_minutes')<p class="field-error col-span-2" role="alert">{{ $message }}</p>@enderror
                        <div class="col-span-2">
                            <label for="rule-due" class="field-label !mt-0">Due, days after it’s created</label>
                            <input id="rule-due" type="number" name="due_in_days" min="0" max="365" value="{{ old('due_in_days', 0) }}" class="field-input field-sm !mt-1">
                        </div>
                    </div>
                    <button class="btn-primary btn-sm">Add recurring task</button>
                </form>
            </x-live-region>
        </div>
    </div>
</x-app-layout>
