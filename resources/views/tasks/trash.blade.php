@php
    use App\Models\Task;
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Trash" description="Deleted tasks stay here for {{ Task::TRASH_DAYS }} days, then go for good." :breadcrumbs="['All tasks' => route('tasks.index'), 'Trash' => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <x-live-region id="task-trash" class="space-y-3">
                @if ($tasks->isEmpty())
                    <x-empty-state icon="trash" title="The trash is empty." />
                @else
                    <div class="table-wrap">
                        <table class="table-base">
                            <thead>
                                <tr>
                                    <th scope="col" class="w-20">Key</th>
                                    <th scope="col">Task</th>
                                    <th scope="col" class="hidden md:table-cell">Deleted by</th>
                                    <th scope="col">Goes in</th>
                                    <th scope="col"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tasks as $task)
                                    @php $daysLeft = (int) max(0, ceil(now()->diffInDays($task->deleted_at->copy()->addDays(Task::TRASH_DAYS)))); @endphp
                                    <tr>
                                        <td class="tabular whitespace-nowrap text-xs text-slate-500">{{ $task->reference() }}</td>
                                        <td class="max-w-[28rem]">
                                            <span class="block truncate font-medium text-slate-900" title="{{ $task->title }}">{{ $task->title }}</span>
                                            @if ($task->release)
                                                <span class="block truncate text-xs text-slate-500">{{ $task->release->name }}</span>
                                            @endif
                                        </td>
                                        <td class="hidden whitespace-nowrap text-sm text-slate-600 md:table-cell" title="{{ $task->deleted_at->format('M j, Y g:i a') }}">
                                            {{ $task->deletedBy?->name ?? 'Unknown' }} · {{ $task->deleted_at->diffForHumans() }}
                                        </td>
                                        <td class="tabular whitespace-nowrap text-sm text-slate-600">{{ $daysLeft }} {{ Str::plural('day', $daysLeft) }}</td>
                                        <td class="whitespace-nowrap text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <form method="POST" action="{{ route('tasks.restore') }}">
                                                    @csrf
                                                    <input type="hidden" name="ids[]" value="{{ $task->id }}">
                                                    <button class="btn-secondary btn-sm">Restore</button>
                                                </form>
                                                <form method="POST" action="{{ route('tasks.trash.destroy', $task->id) }}"
                                                      data-confirm-title="Delete for good?" data-confirm-verb="Delete for good"
                                                      data-confirm="“{{ Str::limit($task->title, 80) }}” and its subtasks, comments and logged time will be gone, and can’t be brought back.">
                                                    @csrf @method('DELETE')
                                                    <button class="btn-ghost btn-sm text-danger-600">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{ $tasks->links() }}
                @endif
            </x-live-region>
        </div>
    </div>
</x-app-layout>
