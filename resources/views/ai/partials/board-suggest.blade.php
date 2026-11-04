{{-- "Suggest an assignee" on a board card. CSS shows it only while the card is unassigned; the page holds one dialog. --}}
@if (app(\App\Services\Ai\AiFeatures::class)->available('assignee_suggest') && ! auth()->user()->isViewer())
    <button type="button" x-data class="board-suggest" draggable="false" title="Suggest an assignee"
            aria-label="Suggest an assignee for “{{ $task->title }}”"
            @click="$dispatch('ai-suggest-assignee', { url: {{ Js::from(route('ai.tasks.suggest-assignee', $task)) }}, assignUrl: {{ Js::from(route('board.tasks.assign', $task)) }}, title: {{ Js::from($task->title) }}, trigger: $el })">
        <x-icon name="sparkles" variant="outline" class="h-3.5 w-3.5" />
    </button>
@endif
