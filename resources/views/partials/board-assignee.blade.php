{{-- A card's assignee control. Requires $task with assignee and assignees loaded. The board's script
     opens the picker from it, and the assign endpoint re-renders it, so avatars are only ever drawn here. --}}
@php
    $people = $task->assigneeList();
    $names = $people->pluck('name')->implode(', ');
@endphp
<button type="button" class="board-assign" draggable="false" data-url="{{ route('board.tasks.assignees', $task) }}"
        data-assign-url="{{ route('board.tasks.assign', $task) }}" data-title="{{ $task->title }}"
        aria-haspopup="dialog"
        aria-label="{{ $people->isNotEmpty() ? 'Assigned to '.$names.'. Change the assignees of “'.$task->title.'”' : 'Unassigned. Assign “'.$task->title.'”' }}"
        title="{{ $people->isNotEmpty() ? $names : 'Assign someone' }}">
    @if ($people->count() > 1)
        @include('partials.assignee-stack', ['people' => $people, 'size' => 'sm', 'names' => 'none'])
        <span class="truncate text-xs text-slate-600">{{ $people->first()->name }} <span class="text-slate-400">+{{ $people->count() - 1 }}</span></span>
    @elseif ($people->isNotEmpty())
        <x-avatar :user="$people->first()" size="sm" />
        <span class="truncate text-xs text-slate-600">{{ $people->first()->name }}</span>
    @else
        <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full border border-dashed border-slate-300 text-slate-400" aria-hidden="true">
            <x-icon name="user-plus" variant="outline" class="h-3.5 w-3.5" />
        </span>
        <span class="truncate text-xs text-slate-500">Unassigned</span>
    @endif
</button>
