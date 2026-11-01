{{--
    Claude on a meeting note: turn its action items into board tasks (#2).
    @include('ai.panels.meeting-note', ['meetingNote' => $meetingNote])
--}}
@if (app(\App\Services\Ai\AiFeatures::class)->available('meeting_actions') && ! \App\Support\HtmlSanitizer::isEmpty($meetingNote->body))
    <section class="card" aria-labelledby="ai-actions-{{ $meetingNote->id }}"
             x-data="aiSuggestions({
                proposeUrl: @js(route('ai.meeting-actions', $meetingNote)),
                createUrl: @js(route('ai.meeting-actions.store', $meetingNote)),
             })">
        <div class="card-header flex items-center gap-2">
            <x-icon name="sparkles" variant="outline" class="h-4 w-4 text-violet-600" />
            <h2 id="ai-actions-{{ $meetingNote->id }}" class="card-title">Action items → board</h2>
        </div>
        <div class="px-5 pb-5 text-sm">
            <p class="text-slate-500" x-show="items === null">
                Claude finds the action items in these minutes, with owners and due dates, and proposes them as tasks{{ $meetingNote->release ? ' in '.$meetingNote->release->name : '' }}.
            </p>
            <div class="mt-3" x-show="items === null">
                <x-ai-button label="Find action items" busy-label="Reading the minutes…" click="propose()" />
            </div>
            @include('ai.partials.suggestions', ['withPeople' => true, 'withDates' => true])
            <x-ai-error class="mt-2" />
        </div>
    </section>
@endif
