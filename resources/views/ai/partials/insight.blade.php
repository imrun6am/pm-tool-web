{{--
    A background Claude result with its request button and live progress.
    @include('ai.partials.insight', [
        'kind' => AiInsight::KIND_RELEASE_RISK, 'subject' => $release, 'body' => ['subject_id' => $release->id],
        'canRequest' => bool, 'label' => 'Assess risk', 'empty' => 'No brief yet.', 'forDate' => ?Carbon,
    ])
    Renders the newest finished result right away; a running one keeps polling.
--}}
@php
    $insights = app(\App\Services\Ai\Insights\AiInsightService::class);
    $forDate = $forDate ?? null;
    $latest = $insights->latest($kind, $subject, $forDate);
    $ready = $latest?->isReady() ? $latest : $insights->latestReady($kind, $subject, $forDate);
    $canRequest = $canRequest ?? false;
@endphp
<div x-data="aiInsight({
        storeUrl: @js(route('ai.insights.store')),
        statusUrl: @js(route('ai.insights.show', '__ID__')),
        body: @js(['kind' => $kind] + $body),
        insight: @js($latest?->toStatus()),
     })">
    <div x-ref="body">
        @if ($ready)
            @include("ai.insights.{$kind}", ['insight' => $ready])
        @else
            <p class="text-sm text-slate-500" x-show="!running">{{ $empty }}</p>
        @endif
    </div>

    <div x-show="running" x-cloak class="mt-3 flex items-center gap-2 text-sm text-violet-700" role="status">
        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3" />
            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
        </svg>
        Claude is working on it — this can take a minute. You can leave this page.
    </div>

    <x-ai-error class="mt-3" />

    <div class="mt-3 flex flex-wrap items-center justify-between gap-2" x-show="!running">
        <span class="text-xs text-slate-400" x-show="generated" x-text="'Generated ' + generated"></span>
        @if ($canRequest)
            <x-ai-button :label="$ready ? ($refresh ?? 'Refresh') : $label" busy-label="Starting…" click="start()" />
        @endif
    </div>
</div>
