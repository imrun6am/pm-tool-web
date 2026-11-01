@php
    /** @var \Illuminate\Support\Collection $onboardingSteps  (EngagementServiceProvider composer) */
    $done = $onboardingSteps->where('done', true)->count();
    $total = $onboardingSteps->count();
    $firstName = \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first();
    $cta = [
        'add_task' => 'Add a task',
        'finish_task' => 'Open the board',
        'comment' => 'Find a task',
        'tasksheet' => 'Open tasksheet',
        'release' => 'Plan one',
    ];
@endphp
{{-- Dashboard "Get started" card (onboarding-checklist). Renders nothing once
     every step is done or the user dismissed it. --}}
@if ($onboardingVisible)
    <section class="card relative overflow-hidden" aria-labelledby="onboarding-title">
        <div class="pointer-events-none absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-sky-400 via-brand-500 to-brand-700" aria-hidden="true"></div>
        <div class="flex flex-wrap items-start justify-between gap-4 px-5 pb-2 pt-5 sm:px-6">
            <div class="min-w-0">
                <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-brand-700">
                    <x-icon name="sparkles" variant="outline" class="h-4 w-4" /> Get started
                </p>
                <h2 id="onboarding-title" class="mt-1 text-base font-semibold text-slate-900">{{ $firstName }}, a few quick steps to feel at home</h2>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 text-sm text-slate-600">
                    <span class="h-2 w-24 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="Getting started progress"
                          aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $done }}">
                        <span class="block h-full rounded-full bg-gradient-to-r from-sky-400 to-brand-600 transition-[width] duration-500 motion-reduce:transition-none" style="width: {{ $total ? $done / $total * 100 : 0 }}%"></span>
                    </span>
                    <span class="font-medium tabular-nums">{{ $done }} of {{ $total }}</span>
                </div>
                <form method="POST" action="{{ route('onboarding.dismiss') }}">
                    @csrf
                    <button type="submit" class="btn-ghost btn-sm btn-icon text-slate-400" aria-label="Dismiss getting started" title="Dismiss">
                        <x-icon name="x-mark" variant="outline" class="h-5 w-5" />
                    </button>
                </form>
            </div>
        </div>

        <ol @class(['grid gap-3 px-5 py-4 sm:px-6', 'sm:grid-cols-2 xl:grid-cols-4' => $total === 4, 'sm:grid-cols-3' => $total === 3])>
            @foreach ($onboardingSteps as $step)
                <li @class([
                    'flex flex-col rounded-xl border p-4 transition-colors',
                    'border-emerald-100 bg-emerald-50/50' => $step['done'],
                    'border-slate-200 bg-white' => ! $step['done'],
                ])>
                    <div class="flex items-start gap-3">
                        @if ($step['done'])
                            <span class="flex h-7 w-7 flex-none items-center justify-center rounded-full bg-emerald-500 text-white">
                                <x-icon name="check" variant="outline" class="h-4 w-4" />
                            </span>
                        @else
                            <span class="flex h-7 w-7 flex-none items-center justify-center rounded-full border-2 border-slate-200 text-xs font-semibold text-slate-500">{{ $loop->iteration }}</span>
                        @endif
                        <div class="min-w-0">
                            <p @class(['text-sm font-semibold', 'text-emerald-800 line-through decoration-emerald-300' => $step['done'], 'text-slate-900' => ! $step['done']])>{{ $step['label'] }}</p>
                            <p class="mt-0.5 text-sm text-slate-500">{{ $step['description'] }}</p>
                        </div>
                    </div>
                    @unless ($step['done'])
                        <a href="{{ $step['action'] === 'quick-capture' ? route('tasks.quick') : route($step['action']) }}"
                           class="mt-3 inline-flex items-center gap-1 self-start pl-10 text-sm font-medium text-brand-700 hover:text-brand-800">
                            {{ $cta[$step['key']] ?? 'Go' }} <x-icon name="arrow-right" variant="outline" class="h-4 w-4" />
                        </a>
                    @else
                        <span class="sr-only">Done</span>
                    @endunless
                </li>
            @endforeach
        </ol>

        <p class="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-t border-slate-100 bg-slate-50/70 px-5 py-2.5 text-xs text-slate-500 sm:px-6">
            <span class="font-medium text-slate-600">Shortcuts</span>
            <span class="flex items-center gap-1.5"><kbd class="kbd">⌘</kbd><kbd class="kbd">K</kbd> jump anywhere</span>
            <span class="flex items-center gap-1.5"><kbd class="kbd">C</kbd> add a task</span>
            <span class="flex items-center gap-1.5"><kbd class="kbd">?</kbd> all shortcuts</span>
        </p>
    </section>
@endif
