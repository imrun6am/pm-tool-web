{{--
    Claude on the release page: risk brief (#3), release notes (#1), comment
    catch-up (#6) and document insights (#7). Each card shows only when its
    feature is available and the viewer may use it.
    @include('ai.panels.release', ['release' => $release])
--}}
@php
    use App\Models\AiInsight;
    use App\Services\Ai\ThreadCatchUp;

    $ai = app(\App\Services\Ai\AiFeatures::class);
    $viewer = auth()->user();
    $planner = $viewer->canManageReleases();
    $showRisk = ! $viewer->hasLimitedAccess() && $ai->available('release_risk');
    $showNotes = filled($release->release_notes) || ($planner && $ai->available('release_notes'));
    $showCatchUp = $ai->available('catch_up') && $release->comments->count() >= ThreadCatchUp::MIN_COMMENTS;
    $showDocs = $ai->available('document_insights') && $release->documents->isNotEmpty();
    $cardHead = 'flex items-center gap-2 border-b border-slate-100 px-5 py-4';
@endphp

@if ($showRisk)
    <section class="card" aria-labelledby="ai-risk-{{ $release->id }}">
        <div class="{{ $cardHead }}">
            <x-icon name="sparkles" variant="outline" class="h-4 w-4 text-violet-600" />
            <h3 id="ai-risk-{{ $release->id }}" class="text-sm font-semibold text-slate-700">Risk brief</h3>
        </div>
        <div class="px-5 py-4">
            @include('ai.partials.insight', [
                'kind' => AiInsight::KIND_RELEASE_RISK,
                'subject' => $release,
                'body' => ['subject_id' => $release->id],
                'canRequest' => $planner,
                'label' => 'Assess risk with Claude',
                'empty' => $planner ? 'Ask Claude whether this release is on track, and why.' : 'No risk brief yet — a release planner can generate one.',
            ])
        </div>
    </section>
@endif

@if ($showNotes)
    <section class="card" aria-labelledby="ai-notes-{{ $release->id }}"
             x-data="aiNotes">
        <div class="{{ $cardHead }} justify-between">
            <div class="flex items-center gap-2">
                <x-icon name="document-text" variant="outline" class="h-4 w-4 text-violet-600" />
                <h3 id="ai-notes-{{ $release->id }}" class="text-sm font-semibold text-slate-700">Release notes</h3>
            </div>
            @if ($planner)
                <button type="button" x-show="!editing" @click="editing = true" class="text-xs font-medium text-brand-600 hover:text-brand-700">
                    {{ filled($release->release_notes) ? 'Edit' : 'Write' }}
                </button>
            @endif
        </div>
        <div class="px-5 py-4">
            <div x-show="!editing">
                @if (filled($release->release_notes))
                    <div class="prose-notes text-sm">{!! $release->release_notes !!}</div>
                @else
                    <p class="text-sm text-slate-500">No release notes yet. Claude can draft them from the finished tasks.</p>
                @endif
            </div>

            @if ($planner)
                <form x-show="editing" x-cloak method="POST" action="{{ route('ai.release-notes.update', $release) }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    <input id="release-notes-{{ $release->id }}" type="hidden" name="release_notes" value="{{ $release->release_notes }}">
                    <trix-editor input="release-notes-{{ $release->id }}" class="prose-notes min-h-[10rem] bg-white"
                                 placeholder="What shipped, for readers outside the team…"></trix-editor>
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($ai->available('release_notes'))
                            <x-ai-button label="Draft with Claude" busy-label="Drafting…"
                                         click="draft(@js(route('ai.release-notes.draft', $release)), 'release-notes-{{ $release->id }}')" />
                        @endif
                        <button type="submit" class="btn btn-primary btn-sm">Save notes</button>
                        <button type="button" @click="editing = false" class="btn btn-ghost btn-sm">Cancel</button>
                    </div>
                    <p x-show="drafted" x-cloak class="text-xs text-slate-500" role="status"
                       x-text="'Drafted from ' + drafted + ' finished ' + (drafted === 1 ? 'task' : 'tasks') + ' — review it, then save.'"></p>
                    <x-ai-error />
                </form>
            @endif
        </div>
    </section>
@endif

@if ($showCatchUp)
    @include('ai.partials.catch-up', ['url' => route('ai.catch-up.release', $release), 'count' => $release->comments->count()])
@endif

@if ($showDocs)
    <section class="card" aria-labelledby="ai-docs-{{ $release->id }}">
        <div class="{{ $cardHead }}">
            <x-icon name="sparkles" variant="outline" class="h-4 w-4 text-violet-600" />
            <h3 id="ai-docs-{{ $release->id }}" class="text-sm font-semibold text-slate-700">Document insights</h3>
        </div>
        <ul class="divide-y divide-slate-100">
            @foreach ($release->documents as $document)
                <li class="px-5 py-3" x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-2 text-left"
                            :aria-expanded="open">
                        <span class="truncate text-sm font-medium text-slate-800">{{ $document->original_name }}</span>
                        <x-icon name="chevron-right" variant="outline" class="h-4 w-4 flex-none text-slate-400 transition" x-bind:class="open && 'rotate-90'" />
                    </button>
                    <div x-show="open" x-cloak class="mt-3">
                        @include('ai.partials.insight', [
                            'kind' => AiInsight::KIND_DOCUMENT,
                            'subject' => $document,
                            'body' => ['subject_id' => $document->id, 'release_id' => $release->id],
                            'canRequest' => ! $viewer->isViewer(),
                            'label' => 'Analyze with Claude',
                            'refresh' => 'Analyze again',
                            'empty' => 'Claude can summarize this document, list its requirements and suggest tasks.',
                        ])
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
@endif
