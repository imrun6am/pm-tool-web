@php
    // Requires $task, with blockers and blocking loaded through TaskDependencyService::loadLinks.
    $isDone = in_array($task->status, \App\Models\Task::DONE_STATUSES, true);
    $canEdit = auth()->user()->can('update', $task);
@endphp
<x-live-region id="task-blocking">
    <section class="card" aria-labelledby="blocking-title">
        <div class="card-header">
            <h2 id="blocking-title" class="card-title flex items-center gap-2">
                Dependencies
                @if ($task->isBlocked())
                    <span class="badge badge-danger">Blocked</span>
                @endif
            </h2>
            @if ($canEdit && ! $task->blocked_at && ! $isDone)
                <div x-data="{ open: {{ $errors->has('reason') ? 'true' : 'false' }} }" class="relative">
                    <button type="button" @click="open = ! open" class="btn-ghost btn-sm" :aria-expanded="open.toString()">Mark as blocked</button>
                    <form x-show="open" x-cloak @click.outside="open = false" method="POST" action="{{ route('tasks.block', $task) }}"
                          class="absolute right-0 z-20 mt-2 w-72 space-y-2 rounded-xl border border-slate-200 bg-white p-3 shadow-lg">
                        @csrf
                        <label for="block-reason" class="field-label !mt-0">What is it waiting for?</label>
                        <input id="block-reason" name="reason" required maxlength="500" value="{{ old('reason') }}" class="field-control field-sm" autocomplete="off">
                        <button class="btn-primary btn-sm">Mark as blocked</button>
                    </form>
                </div>
            @endif
        </div>

        @error('reason')<p class="field-error border-b border-slate-100 px-5 py-3" role="alert">{{ $message }}</p>@enderror
        @error('reference')<p class="field-error border-b border-slate-100 px-5 py-3" role="alert">{{ $message }}</p>@enderror

        @if ($task->blocked_at)
            <div class="flex items-start gap-3 border-b border-rose-100 bg-rose-50/60 px-5 py-3">
                <div class="min-w-0 flex-1">
                    <p class="break-words text-sm text-rose-800">{{ $task->blocked_reason }}</p>
                    <p class="mt-0.5 text-xs text-rose-600" title="{{ $task->blocked_at->format('M j, Y g:i a') }}">Blocked {{ $task->blocked_at->diffForHumans() }}</p>
                </div>
                @if ($canEdit)
                    <form method="POST" action="{{ route('tasks.unblock', $task) }}" class="flex-none">
                        @csrf @method('DELETE')
                        <button class="btn-secondary btn-sm">Unblock</button>
                    </form>
                @endif
            </div>
        @endif

        <div class="space-y-4 px-5 py-4">
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Waiting for</h3>
                <ul class="mt-2 space-y-1.5">
                    @forelse ($task->blockers as $blocker)
                        <li class="flex items-center gap-2 text-sm">
                            <span class="tabular flex-none text-xs text-slate-500">{{ $blocker->reference() }}</span>
                            <a href="{{ route('tasks.show', $blocker) }}" class="min-w-0 flex-1 truncate font-medium text-slate-800 hover:text-brand-700">{{ $blocker->title }}</a>
                            @include('partials.status-badge', ['status' => $blocker->status])
                            @if ($canEdit)
                                <form method="POST" action="{{ route('tasks.dependencies.destroy', [$task, $blocker]) }}" class="flex-none">
                                    @csrf @method('DELETE')
                                    <button class="btn-ghost btn-sm btn-icon h-7 w-7 text-slate-400 hover:text-danger-600" aria-label="Stop waiting for {{ $blocker->reference() }}">
                                        <x-icon name="x-mark" variant="outline" class="h-4 w-4" />
                                    </button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">Nothing.</li>
                    @endforelse
                </ul>
                @if ($canEdit)
                    <form method="POST" action="{{ route('tasks.dependencies.store', $task) }}" class="mt-2.5 flex gap-2">
                        @csrf
                        <label for="dependency-reference" class="sr-only">Task to wait for</label>
                        <input id="dependency-reference" name="reference" required maxlength="40" placeholder="{{ \App\Models\Task::KEY_PREFIX }}-123" value="{{ old('reference') }}"
                               class="field-control field-sm w-32" autocomplete="off">
                        <button class="btn-secondary btn-sm">Add</button>
                    </form>
                @endif
            </div>

            @if ($task->blocking->isNotEmpty())
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Holding up</h3>
                    <ul class="mt-2 space-y-1.5">
                        @foreach ($task->blocking as $waiting)
                            <li class="flex items-center gap-2 text-sm">
                                <span class="tabular flex-none text-xs text-slate-500">{{ $waiting->reference() }}</span>
                                <a href="{{ route('tasks.show', $waiting) }}" class="min-w-0 flex-1 truncate font-medium text-slate-800 hover:text-brand-700">{{ $waiting->title }}</a>
                                @include('partials.status-badge', ['status' => $waiting->status])
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>
</x-live-region>
