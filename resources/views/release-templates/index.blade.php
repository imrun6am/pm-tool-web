@php
    use App\Models\Release;
    use App\Models\Task;
    use App\Support\Duration;
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Release templates" description="Standard task sets. Apply one from a release’s page; each task is due when its phase ends." :breadcrumbs="['Releases' => route('releases.index'), 'Templates' => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container max-w-4xl">
            <x-live-region id="release-templates" class="space-y-6">
                <form method="POST" action="{{ route('release-templates.store') }}" class="card flex flex-wrap items-end gap-3 p-4">
                    @csrf
                    <div class="min-w-[14rem] flex-1">
                        <label for="template-name" class="field-label !mt-0">New template</label>
                        <input id="template-name" name="name" required maxlength="100" value="{{ old('name') }}" placeholder="Standard release" class="field-control" autocomplete="off">
                    </div>
                    <button class="btn-primary">Add template</button>
                    @error('name')<p class="field-error w-full" role="alert">{{ $message }}</p>@enderror
                </form>

                @foreach (['title', 'phase', 'priority', 'estimate_minutes'] as $field)
                    @error($field)<p class="field-error" role="alert">{{ $message }}</p>@enderror
                @endforeach

                @forelse ($templates as $template)
                    <section class="card" aria-label="{{ $template->name }}">
                        <div class="card-header">
                            <h2 class="card-title">{{ $template->name }}</h2>
                            <form method="POST" action="{{ route('release-templates.destroy', $template) }}" data-confirm="Remove the “{{ $template->name }}” template? Releases that used it keep their tasks." data-confirm-verb="Remove">
                                @csrf @method('DELETE')
                                <button class="btn-ghost btn-sm text-danger-600">Remove</button>
                            </form>
                        </div>
                        <ul class="divide-y divide-slate-100">
                            @foreach ($template->tasks as $task)
                                <li class="flex flex-wrap items-center gap-2 px-5 py-2.5 text-sm">
                                    <span class="min-w-0 flex-1 break-words text-slate-800">{{ $task->title }}</span>
                                    @if ($task->phase)<span class="badge badge-slate">{{ Release::PHASES[$task->phase] ?? $task->phase }}</span>@endif
                                    @if ($task->priority)<span class="badge badge-slate">{{ Task::PRIORITIES[$task->priority] ?? $task->priority }}</span>@endif
                                    @if ($task->estimate_minutes)<span class="tabular text-xs text-slate-500">{{ Duration::format($task->estimate_minutes) }}</span>@endif
                                    <form method="POST" action="{{ route('release-templates.tasks.destroy', [$template, $task]) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn-ghost btn-sm btn-icon h-7 w-7 text-slate-400 hover:text-danger-600" aria-label="Remove {{ $task->title }}">
                                            <x-icon name="x-mark" variant="outline" class="h-4 w-4" />
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                        <form method="POST" action="{{ route('release-templates.tasks.store', $template) }}" class="flex flex-wrap items-center gap-2 border-t border-slate-100 p-4">
                            @csrf
                            <label for="task-title-{{ $template->id }}" class="sr-only">Task title</label>
                            <input id="task-title-{{ $template->id }}" name="title" required maxlength="255" placeholder="Add a task…" class="field-control field-sm min-w-[12rem] flex-1" autocomplete="off">
                            <label for="task-phase-{{ $template->id }}" class="sr-only">Phase</label>
                            <select id="task-phase-{{ $template->id }}" name="phase" data-native class="field-control field-sm w-auto pr-8">
                                <option value="">No phase</option>
                                @foreach (Release::PHASES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <label for="task-priority-{{ $template->id }}" class="sr-only">Priority</label>
                            <select id="task-priority-{{ $template->id }}" name="priority" data-native class="field-control field-sm w-auto pr-8">
                                <option value="">No priority</option>
                                @foreach (Task::PRIORITIES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <label for="task-estimate-{{ $template->id }}" class="sr-only">Estimate</label>
                            <input id="task-estimate-{{ $template->id }}" name="estimate" placeholder="Estimate" class="field-control field-sm w-24" autocomplete="off">
                            <button class="btn-secondary btn-sm">Add</button>
                        </form>
                    </section>
                @empty
                    <x-empty-state icon="rocket" title="No templates yet." description="Add one above, then fill it with the tasks your releases usually need." />
                @endforelse
            </x-live-region>
        </div>
    </div>
</x-app-layout>
