@php
    use App\Models\PerformanceScore;
    use App\Support\ScoreColor;

    /** @var \App\Models\User $rowUser */
    $applicable = $competencies->filter(fn ($c) => $c->appliesToRole($rowUser->role))->values();
    $ratedCount = $applicable->filter(fn ($c) => $scores->has($rowUser->id.'-'.$c->id))->count();
    $leave = $onLeave[$rowUser->id] ?? false;

    // Seed Alpine state from any existing scores/notes for this member + period.
    $seedScores = [];
    $seedNotes = [];
    foreach ($applicable as $c) {
        $s = $scores->get($rowUser->id.'-'.$c->id);
        $seedScores[(string) $c->id] = $s?->score ? (string) $s->score : '';
        $seedNotes[(string) $c->id] = $s?->note ?? '';
    }
@endphp

<div x-data="{ open: false }" class="card overflow-hidden">
    <button type="button" @click="open = ! open"
            class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-slate-50">
        <span class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">
            {{ strtoupper(collect(explode(' ', $rowUser->name))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('')) }}
        </span>
        <span class="min-w-0 flex-1">
            <span class="flex items-center gap-2">
                <span class="truncate text-sm font-semibold text-slate-900">{{ $rowUser->name }}</span>
                @include('partials.role-badge', ['role' => $rowUser->role, 'variant' => 'pill'])
                @if ($leave)
                    <span class="inline-flex rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-medium text-sky-700">On leave</span>
                @endif
            </span>
            <span class="mt-0.5 block text-xs text-slate-400">
                {{ $ratedCount }}/{{ $applicable->count() }} rated this {{ $isWeekly ? 'week' : 'day' }}
            </span>
        </span>
        <span class="flex items-center gap-2">
            <span class="hidden h-1.5 w-24 overflow-hidden rounded-full bg-slate-100 sm:block">
                <span class="block h-full rounded-full bg-brand-500" style="width: {{ $applicable->count() ? round($ratedCount / $applicable->count() * 100) : 0 }}%"></span>
            </span>
            <span x-bind:class="open && 'rotate-180'" class="flex-none text-slate-400 transition">
                <x-icon name="chevron-down" class="h-4 w-4" />
            </span>
        </span>
    </button>

    <div x-show="open" x-cloak style="display: none;" class="border-t border-slate-100 bg-slate-50/60">
        @if ($locked)
            <p class="px-4 py-6 text-sm text-slate-500">This period hasn’t started yet — nothing to rate.</p>
        @else
            <form method="POST" action="{{ route('performance.scores.upsert') }}"
                  x-data="{ scores: {{ Illuminate\Support\Js::from($seedScores) }}, notes: {{ Illuminate\Support\Js::from($seedNotes) }}, noteOpen: {} }"
                  class="space-y-1 px-2 py-2">
                @csrf @method('PUT')
                <input type="hidden" name="team_id" value="{{ $team->id }}">
                <input type="hidden" name="user_id" value="{{ $rowUser->id }}">
                <input type="hidden" name="date" value="{{ $period['start']->toDateString() }}">
                <input type="hidden" name="cadence" value="{{ $cadence }}">

                @foreach ($applicable as $c)
                    <div class="rounded-lg px-2 py-3 hover:bg-white">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-slate-800">{{ $c->name }}</span>
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-500">{{ $c->categoryLabel() }}</span>
                                    <span class="text-[10px] text-slate-400" title="Weight in the blended score">×{{ $c->weight }}</span>
                                </div>
                                @if ($c->description)
                                    <p class="mt-0.5 max-w-xl text-xs text-slate-400">{{ $c->description }}</p>
                                @endif
                            </div>

                            <div class="flex items-center gap-1.5">
                                @for ($n = PerformanceScore::MIN_SCORE; $n <= PerformanceScore::MAX_SCORE; $n++)
                                    <button type="button"
                                            @click="scores['{{ $c->id }}'] = scores['{{ $c->id }}'] == {{ $n }} ? '' : '{{ $n }}'"
                                            :class="scores['{{ $c->id }}'] == {{ $n }} ? 'text-white shadow-sm ring-2 ring-offset-1' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
                                            :style="scores['{{ $c->id }}'] == {{ $n }} ? 'background-color: {{ ScoreColor::hex((float) $n) }}; --tw-ring-color: {{ ScoreColor::hex((float) $n) }}' : ''"
                                            class="flex h-9 w-9 items-center justify-center rounded-lg text-sm font-semibold transition"
                                            aria-label="{{ $n }} — {{ PerformanceScore::label($n) }}"
                                            title="{{ $n }} — {{ PerformanceScore::label($n) }}">{{ $n }}</button>
                                @endfor
                                <button type="button" @click="noteOpen['{{ $c->id }}'] = ! noteOpen['{{ $c->id }}']"
                                        class="ml-1 flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-200 hover:text-slate-600"
                                        :class="notes['{{ $c->id }}'] ? 'text-brand-600' : ''"
                                        title="Add a private note">
                                    <x-icon name="note" class="h-4 w-4" />
                                </button>
                                <input type="hidden" name="scores[{{ $c->id }}]" x-model="scores['{{ $c->id }}']">
                            </div>
                        </div>

                        <div x-show="noteOpen['{{ $c->id }}'] || notes['{{ $c->id }}']" style="display:none;" class="mt-2">
                            <textarea name="notes[{{ $c->id }}]" x-model="notes['{{ $c->id }}']" rows="2"
                                      placeholder="Private note (leads only) — what drove this rating?"
                                      class="field-input w-full text-sm"></textarea>
                        </div>
                    </div>
                @endforeach

                <div class="flex items-center justify-end gap-2 px-2 py-2">
                    <span class="text-xs text-slate-400">Notes are visible to leads only.</span>
                    <button class="btn-primary btn-sm">Save ratings</button>
                </div>
            </form>
        @endif
    </div>
</div>
