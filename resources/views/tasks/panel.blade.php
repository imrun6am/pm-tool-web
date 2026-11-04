@php
    use App\Models\Task;
    use App\Support\Duration;

    $flashes = collect([
        ['type' => 'success', 'message' => session('success')],
        ['type' => 'error', 'message' => session('error')],
        ['type' => 'celebrate', 'message' => session('celebrate')],
    ])->filter(fn ($t) => filled($t['message']))->values();
@endphp
{{-- The task side panel's content, cut out by its Turbo Frame. Every part is its
     own live region, so a change refreshes just that part. --}}
<turbo-frame id="task-panel" target="_top">
    {{-- Read by resources/js/app.js when a region updates, as on a full page. --}}
    <script type="application/json" id="flash-toasts">@json($flashes)</script>

    <div class="space-y-4 p-4 sm:p-5">
        <x-live-region id="panel-details">
            <form method="POST" action="{{ route('tasks.update', $task) }}" class="card space-y-3 p-4">
                @csrf @method('PUT')
                <div class="flex items-center gap-2">
                    <span class="tabular text-xs font-semibold text-slate-500">{{ $task->reference() }}</span>
                    @if ($task->isBlocked())
                        <span class="badge badge-danger">Blocked</span>
                    @endif
                    <a href="{{ route('tasks.show', $task) }}" class="ml-auto text-xs font-medium text-brand-700 hover:text-brand-800">Open full page</a>
                </div>

                <div>
                    <label for="panel-title" class="sr-only">Title</label>
                    <input id="panel-title" name="title" required maxlength="255" value="{{ old('title', $task->title) }}"
                           class="field-control text-base font-semibold" autocomplete="off">
                    @error('title')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="panel-description" class="field-label !mt-0">Description</label>
                    <textarea id="panel-description" name="description" rows="3" maxlength="5000" class="field-control">{{ old('description', $task->description) }}</textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="panel-status" class="field-label !mt-0">Status</label>
                        <select id="panel-status" name="status" class="field-select field-sm !mt-1">
                            @foreach (Task::STATUSES as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $task->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="panel-priority" class="field-label !mt-0">Priority</label>
                        <select id="panel-priority" name="priority" class="field-select field-sm !mt-1">
                            <option value="">No priority</option>
                            @foreach (Task::PRIORITIES as $value => $label)
                                <option value="{{ $value }}" @selected(old('priority', $task->priority) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label for="panel-assignee" class="field-label !mt-0">Assignee</label>
                        <select id="panel-assignee" name="assignee_id" class="field-select field-sm !mt-1">
                            <option value="">Unassigned</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected((int) old('assignee_id', $task->assignee_id) === $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                        @error('assignee_id')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="panel-due" class="field-label !mt-0">Due date</label>
                        <input id="panel-due" type="date" name="due_date" value="{{ old('due_date', optional($task->due_date)->toDateString()) }}" class="field-input field-sm !mt-1">
                        @error('due_date')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="panel-estimate" class="field-label !mt-0">Estimate</label>
                        <input id="panel-estimate" name="estimate" placeholder="4h 30m" autocomplete="off"
                               value="{{ old('estimate', $task->estimate_minutes ? Duration::format($task->estimate_minutes) : '') }}" class="field-input field-sm !mt-1">
                        @error('estimate_minutes')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                </div>

                <fieldset>
                    <legend class="field-label !mt-0">Labels</legend>
                    <input type="hidden" name="label_ids_sent" value="1">
                    <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1.5">
                        @php $chosen = array_map('intval', old('label_ids', $task->labels->pluck('id')->all())); @endphp
                        @foreach ($labels as $l)
                            <label class="flex cursor-pointer items-center gap-1.5 text-xs text-slate-700">
                                <input type="checkbox" name="label_ids[]" value="{{ $l->id }}" @checked(in_array($l->id, $chosen, true)) class="checkbox-sm">
                                <span class="h-2 w-2 flex-none rounded-full" style="background-color: {{ $l->color }}" aria-hidden="true"></span>
                                {{ $l->name }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <button class="btn-primary btn-sm">Save</button>
            </form>
        </x-live-region>

        @include('tasks._blocking')

        @if ($task->subtasks->isNotEmpty())
            <section class="card" aria-labelledby="panel-subtasks-title">
                <div class="card-header"><h2 id="panel-subtasks-title" class="card-title">Subtasks</h2></div>
                <ul class="divide-y divide-slate-100">
                    @foreach ($task->subtasks as $sub)
                        <li class="flex items-center gap-2 px-5 py-2.5 text-sm">
                            <a href="{{ route('tasks.show', $sub) }}" class="min-w-0 flex-1 truncate font-medium text-slate-800 hover:text-brand-700">{{ $sub->title }}</a>
                            @include('partials.status-badge', ['status' => $sub->status])
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @include('tasks._time-log')

        <x-live-region id="panel-comments">
            <section class="card" aria-labelledby="panel-comments-title">
                <div class="card-header"><h2 id="panel-comments-title" class="card-title">Comments</h2></div>
                <div class="p-4">
                    @include('partials.comments', ['comments' => $task->comments, 'storeUrl' => route('tasks.comments.store', $task)])
                </div>
            </section>
        </x-live-region>
    </div>
</turbo-frame>
