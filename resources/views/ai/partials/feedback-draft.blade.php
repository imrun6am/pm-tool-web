{{--
    "Draft feedback" (#11) under a tasksheet row's lead-only feedback editor.
    Reads the row's current plan/result/comment/tickets from the editor's
    hidden inputs (so unsaved edits count) and loads the draft into the
    feedback editor; the row is saved by its own Save.
    @include('ai.partials.feedback-draft', ['idp' => $idp, 'team' => $team, 'rowUser' => $rowUser, 'day' => $day])
--}}
@if ($team && app(\App\Services\Ai\AiFeatures::class)->available('feedback_draft'))
    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5"
         x-data="aiPanel({ url: @js(route('ai.tasksheet.feedback')) })">
        <x-ai-button label="Draft feedback" busy-label="Drafting…"
                     click="run({
                        team_id: {{ $team->id }}, user_id: {{ $rowUser->id }}, date: @js($day->toDateString()),
                        plan: document.getElementById(@js($idp.'-plan'))?.value ?? '',
                        result: document.getElementById(@js($idp.'-result'))?.value ?? '',
                        comment: document.getElementById(@js($idp.'-comment'))?.value ?? '',
                        tickets: document.getElementById(@js($idp.'-tickets'))?.value ?? '',
                     }).then((r) => r && window.aiFillField(@js($idp.'-feedback'), r.html, true))" />
        <span x-show="result && !busy" x-cloak class="text-xs text-slate-600" role="status">Drafted — edit it, then save the row.</span>
        <x-ai-error />
    </div>
@endif
