{{-- Status updates on the release. Requires $release and $statusUpdates. --}}
@php
    use App\Models\ReleaseStatusUpdate;

    $planner = auth()->user()->canManageReleases();
    $canDraft = $planner && app(\App\Services\Ai\AiFeatures::class)->available('release_status');
    $latest = $statusUpdates->first();
    $earlier = $statusUpdates->slice(1);
    $look = [
        'on_track' => ['badge' => 'badge-success', 'icon' => 'check'],
        'at_risk' => ['badge' => 'badge-warning', 'icon' => 'exclamation-circle'],
        'off_track' => ['badge' => 'badge-danger', 'icon' => 'x-mark'],
    ];
@endphp
<x-live-region id="release-status" class="card">
    <div class="card-header">
        <h2 class="card-title flex items-center gap-2">
            <x-icon name="flag" variant="outline" class="h-4 w-4 text-slate-400" />Status
        </h2>
        @if ($latest)
            <span class="badge {{ $look[$latest->status]['badge'] }} inline-flex items-center gap-1">
                <x-icon :name="$look[$latest->status]['icon']" variant="outline" class="h-3.5 w-3.5" />{{ $latest->statusLabel() }}
            </span>
        @endif
    </div>
    <div class="space-y-4 px-5 py-4">
        @if ($latest)
            <div>
                <p class="whitespace-pre-line text-sm leading-6 text-slate-700">{{ $latest->note }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $latest->author?->name ?? 'Former user' }} · <time datetime="{{ $latest->created_at->toIso8601String() }}" title="{{ $latest->created_at->format('M j, Y g:i A') }}">{{ $latest->created_at->diffForHumans() }}</time></p>
            </div>
        @else
            <p class="text-sm text-slate-500">No status update yet.</p>
        @endif

        @if ($planner)
            <details @class(['group', 'border-t border-slate-100 pt-4' => $latest]) @if ($errors->has('note') || $errors->has('status')) open @endif>
                <summary class="cursor-pointer text-sm font-medium text-brand-700 hover:text-brand-800">Post an update</summary>
                <form method="POST" action="{{ route('releases.status-updates.store', $release) }}" class="mt-3 space-y-3"
                      @if ($canDraft) x-data="aiPanel({ url: @js(route('ai.release-status.draft', $release)) })" @endif>
                    @csrf
                    <div>
                        <label for="status-update-status" class="field-label">Status</label>
                        <select id="status-update-status" name="status" required data-native x-ref="status" class="field-control field-sm mt-1 w-full">
                            @foreach (ReleaseStatusUpdate::STATUSES as $value => $name)
                                <option value="{{ $value }}" @selected(old('status', $latest?->status ?? 'on_track') === $value)>{{ $name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-1" />
                    </div>
                    <div>
                        <label for="status-update-note" class="field-label">Where it stands</label>
                        <textarea id="status-update-note" name="note" rows="4" required maxlength="2000" x-ref="note" class="field-control mt-1 w-full">{{ old('note') }}</textarea>
                        <x-input-error :messages="$errors->get('note')" class="mt-1" />
                    </div>
                    @if ($canDraft)
                        <x-ai-error />
                    @endif
                    <div class="flex flex-wrap items-center gap-2">
                        <button class="btn-primary btn-sm">Post update</button>
                        @if ($canDraft)
                            <x-ai-button label="Draft with AI" busy-label="Drafting…"
                                         click="run().then((d) => { if (d) { $refs.status.value = d.status; $refs.note.value = d.note; $refs.note.focus(); } })" />
                        @endif
                    </div>
                </form>
            </details>
        @endif

        @if ($earlier->isNotEmpty())
            <details class="border-t border-slate-100 pt-4">
                <summary class="cursor-pointer text-sm font-medium text-slate-600 hover:text-slate-800">Earlier updates ({{ $earlier->count() }})</summary>
                <ol class="mt-3 space-y-3">
                    @foreach ($earlier as $update)
                        <li>
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                                <span class="badge {{ $look[$update->status]['badge'] }} inline-flex items-center gap-1">
                                    <x-icon :name="$look[$update->status]['icon']" variant="outline" class="h-3 w-3" />{{ $update->statusLabel() }}
                                </span>
                                {{ $update->author?->name ?? 'Former user' }} · {{ $update->created_at->format('M j, Y') }}
                            </p>
                            <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $update->note }}</p>
                        </li>
                    @endforeach
                </ol>
            </details>
        @endif
    </div>
</x-live-region>
