{{--
    "Draft a retrospective" in the release's Mark complete form. The draft is added
    to the notes box, and nothing is saved until the form is confirmed.
    @include('ai.partials.release-retro', ['release' => $release])
--}}
@if (app(\App\Services\Ai\AiFeatures::class)->available('release_retro'))
    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5" x-data="aiPanel({ url: @js(route('ai.release-retro', $release)) })">
        <x-ai-button label="Draft a retrospective" busy-label="Drafting…"
                     click="run().then((r) => r && window.aiAppendField('completion_notes', r.markdown))" />
        <span x-show="result && !busy" x-cloak class="text-xs text-slate-600" role="status">Drafted — edit it, then confirm.</span>
        <x-ai-error />
    </div>
@endif
