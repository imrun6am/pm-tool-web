{{-- A card's assignee control. Requires $task with assignee loaded. The board's script opens the picker
     from it, and the assign endpoint re-renders it, so an avatar is only ever drawn here. --}}
<button type="button" class="board-assign" draggable="false" data-url="{{ route('board.tasks.assignees', $task) }}"
        data-assign-url="{{ route('board.tasks.assign', $task) }}" data-title="{{ $task->title }}"
        aria-label="{{ $task->assignee ? 'Assigned to '.$task->assignee->name.'. Change the assignee of “'.$task->title.'”' : 'Unassigned. Assign “'.$task->title.'”' }}"
        title="{{ $task->assignee ? 'Change assignee' : 'Assign someone' }}">
    @if ($task->assignee)
        <x-avatar :user="$task->assignee" size="sm" />
        <span class="truncate text-xs text-slate-600">{{ $task->assignee->name }}</span>
    @else
        <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full border border-dashed border-slate-300 text-slate-400" aria-hidden="true">
            <x-icon name="user-plus" variant="outline" class="h-3.5 w-3.5" />
        </span>
        <span class="truncate text-xs text-slate-500">Unassigned</span>
    @endif
</button>
