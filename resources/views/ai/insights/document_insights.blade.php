@php
    /** @var \App\Models\AiInsight $insight */
    $p = $insight->payload ?? [];
    $document = $insight->subject;
    $priority = ['must' => 'badge-danger', 'should' => 'badge-warning', 'could' => 'badge-slate'];
    $canAdd = $document && ! auth()->user()->isViewer();
@endphp
<div class="space-y-4 text-sm">
    <p class="leading-6 text-slate-700">{{ $p['summary'] ?? '' }}</p>

    @if (! empty($p['key_points']))
        <div>
            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Key points</h4>
            <ul class="mt-1.5 list-inside list-disc space-y-1 text-slate-700 marker:text-slate-400">
                @foreach ($p['key_points'] as $k)
                    <li>{{ $k }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! empty($p['requirements']))
        <details class="group">
            <summary class="flex cursor-pointer list-none items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                <x-icon name="chevron-right" variant="outline" class="h-3.5 w-3.5 transition group-open:rotate-90" />
                Requirements ({{ count($p['requirements']) }})
            </summary>
            <ul class="mt-2 space-y-1.5">
                @foreach ($p['requirements'] as $r)
                    <li class="flex items-start gap-2 text-slate-700">
                        <span class="badge {{ $priority[$r['priority']] ?? 'badge-slate' }} flex-none uppercase">{{ $r['priority'] }}</span>
                        <span>{{ $r['text'] }}</span>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif

    @foreach (['open_questions' => 'Open questions', 'risks' => 'Risks'] as $key => $title)
        @if (! empty($p[$key]))
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $title }}</h4>
                <ul class="mt-1.5 list-inside list-disc space-y-1 text-slate-700 marker:text-slate-400">
                    @foreach ($p[$key] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endforeach

    @if (! empty($p['suggested_tasks']) && $canAdd)
        <div x-data="aiSuggestions({ createUrl: @js(route('ai.documents.tasks', [$document->release_id, $document])) })"
             x-init="items = @js(collect($p['suggested_tasks'])->map(fn ($t) => $t + ['selected' => false])->all())"
             class="rounded-lg bg-violet-50/60 p-3 ring-1 ring-inset ring-violet-600/10">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-violet-800">Suggested tasks</h4>
            <p class="mt-0.5 text-xs text-slate-500">Tick the ones to add to this release’s board.</p>
            @include('ai.partials.suggestions')
            <x-ai-error class="mt-2" />
        </div>
    @endif
</div>
