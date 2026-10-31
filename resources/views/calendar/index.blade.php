<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-4">
                <h2 class="page-title">Calendar</h2>
                <div class="flex items-center gap-1">
                    <a href="{{ route('calendar.index', ['year' => $prev->year, 'month' => $prev->month]) }}"
                       class="btn-secondary btn-sm !px-2" title="Previous month" aria-label="Previous month">‹</a>
                    <span class="min-w-[9.5rem] text-center text-sm font-semibold text-slate-800">{{ $monthLabel }}</span>
                    <a href="{{ route('calendar.index', ['year' => $next->year, 'month' => $next->month]) }}"
                       class="btn-secondary btn-sm !px-2" title="Next month" aria-label="Next month">›</a>
                    <a href="{{ route('calendar.index') }}" class="btn-ghost btn-sm ml-1">Today</a>
                </div>
            </div>
            <a href="{{ route('events.create') }}" class="btn-primary btn-sm">
                <x-icon name="plus" class="h-4 w-4" />
                New event
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <p class="mb-3 text-xs text-slate-400">Tip: click any day to add an event on that date.</p>

            <div class="card overflow-hidden">
                {{-- Weekday header --}}
                <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50 text-center text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                    @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d)
                        <div class="px-2 py-2.5">{{ $d }}</div>
                    @endforeach
                </div>

                {{-- Weeks --}}
                <div class="divide-y divide-slate-200">
                    @foreach ($weeks as $week)
                        <div class="grid grid-cols-7 divide-x divide-slate-200">
                            @foreach ($week as $day)
                                <div role="button" tabindex="0"
                                     onclick="location.href='{{ route('events.create', ['date' => $day['date']->toDateString()]) }}'"
                                     onkeydown="if(event.key==='Enter'){location.href='{{ route('events.create', ['date' => $day['date']->toDateString()]) }}'}"
                                     class="group relative min-h-[7.5rem] cursor-pointer p-1.5 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500/60
                                            {{ $day['inMonth'] ? 'bg-white hover:bg-brand-50/40' : 'bg-slate-50/60 hover:bg-slate-100/70' }}">
                                    <div class="flex items-center justify-between">
                                        <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-xs
                                            {{ $day['isToday'] ? 'bg-brand-600 font-semibold text-white' : ($day['inMonth'] ? 'text-slate-700' : 'text-slate-400') }}">
                                            {{ $day['date']->day }}
                                        </span>
                                        <span class="hidden h-5 w-5 items-center justify-center rounded text-slate-400 group-hover:flex" title="Add event">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"/></svg>
                                        </span>
                                    </div>

                                    <div class="mt-1 space-y-1">
                                        @foreach (collect($day['events'])->take(3) as $event)
                                            <a href="{{ route('events.show', $event) }}" onclick="event.stopPropagation()"
                                               class="block truncate rounded-md bg-slate-50 px-1.5 py-0.5 text-[11px] font-medium text-slate-700 transition-colors hover:bg-slate-100"
                                               style="border-left: 3px solid {{ $event->typeColor() }}"
                                               title="{{ $event->title }} · {{ $event->timeLabel() }}">
                                                @unless ($event->all_day)<span class="text-slate-400">{{ $event->starts_at->format('ga') }}</span> @endunless{{ $event->title }}
                                            </a>
                                        @endforeach
                                        @if (count($day['events']) > 3)
                                            <span class="block px-1 text-[11px] text-slate-400">+{{ count($day['events']) - 3 }} more</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Legend --}}
            <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-slate-600">
                <span class="font-medium text-slate-500">Types:</span>
                @foreach (\App\Models\Event::TYPES as $key => $label)
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-sm" style="background-color: {{ \App\Models\Event::TYPE_COLORS[$key] }}"></span>{{ $label }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
