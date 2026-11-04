{{--
    "Draft with Claude" under a tasksheet row's plan or result editor. The draft
    is added after what the editor already holds, and the row is saved by its own
    Save. A result sends the plan as it is now, so unsaved edits count.
    @include('ai.partials.tasksheet-draft', ['kind' => 'plan', 'idp' => $idp, 'team' => $team, 'rowUser' => $rowUser, 'day' => $day])
--}}
@if ($team && app(\App\Services\Ai\AiFeatures::class)->available('tasksheet_draft'))
    <div class="mt-2 space-y-1.5" x-data="aiPanel({ url: @js(route('ai.tasksheet.draft')) })">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
            <x-ai-button :label="$kind === 'plan' ? 'Draft from open tasks' : 'Draft from board activity'" busy-label="Drafting…"
                         click="run({
                            team_id: {{ $team->id }}, user_id: {{ $rowUser->id }}, date: {{ Js::from($day->toDateString()) }}, kind: {{ Js::from($kind) }},
                            plan: document.getElementById({{ Js::from($idp.'-plan') }})?.value ?? '',
                            result: document.getElementById({{ Js::from($idp.'-result') }})?.value ?? '',
                         }).then((r) => r && window.aiAppendField({{ Js::from($idp.'-'.$kind) }}, r.html))" />
            <span x-show="result && !busy" x-cloak class="text-xs text-slate-600" role="status">Drafted — edit it, then save the row.</span>
        </div>
        <div x-show="result?.unconfirmed?.length" x-cloak class="rounded-lg bg-warning-50 p-2.5 text-xs text-warning-800 ring-1 ring-inset ring-warning-600/20">
            <p class="font-semibold">Not on the board, so left out. Add them if you did them:</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-4">
                <template x-for="item in result?.unconfirmed ?? []" :key="item">
                    <li x-text="item"></li>
                </template>
            </ul>
        </div>
        <x-ai-error />
    </div>
@endif
