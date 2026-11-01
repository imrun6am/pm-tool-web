<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$event->title"
                       :breadcrumbs="['Calendar' => route('calendar.index', ['year' => $event->starts_at->year, 'month' => $event->starts_at->month]), $event->title => null]">
            @can('delete', $event)
                <x-slot name="actions">
                    @can('update', $event)
                        <a href="{{ route('events.edit', $event) }}" @click.prevent="$dispatch('open-event-drawer')" class="btn-secondary btn-sm">
                            <x-icon name="pencil-square" variant="outline" class="h-4 w-4" />Edit
                        </a>
                    @endcan
                    <form method="POST" action="{{ route('events.destroy', $event) }}"
                          data-confirm="{{ $event->created_by === auth()->id() ? 'Delete this event?' : 'Delete '.($event->creator?->name ?? 'this user').'’s event for everyone?' }}" data-confirm-verb="Delete">
                        @csrf @method('DELETE')
                        <button class="btn-danger btn-sm">
                            <x-icon name="trash" variant="outline" class="h-4 w-4" />Delete
                        </button>
                    </form>
                </x-slot>
            @endcan
        </x-page-header>
        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-slate-500">
            <span class="badge badge-slate">
                <span class="h-2 w-2 rounded-full" style="background-color: {{ $event->typeColor() }}"></span>{{ $event->typeLabel() }}
            </span>
            @if ($event->isPrivate())
                <span class="badge badge-slate" title="Only {{ $event->creator?->name ?? 'the creator' }} and the attendees can see this event">
                    <x-icon name="lock-closed" variant="outline" class="h-3 w-3" />Private
                </span>
            @else
                <span class="badge badge-info" title="Everyone who can see the calendar can see this event">
                    <x-icon name="globe" variant="outline" class="h-3 w-3" />Shared
                </span>
            @endif
            <span>
                {{ $event->starts_at->format('l, F j, Y') }}
                @if ($event->isMultiDay()) – {{ $event->endOrStart()->format('l, F j, Y') }} @endif
                <span aria-hidden="true" class="mr-1 text-slate-300">·</span>{{ $event->timeLabel() }}
            </span>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            <div class="mx-auto grid max-w-6xl items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">

                {{-- Main column: description, then the meeting notes --}}
                <div class="min-w-0 space-y-6">
                    <section class="card">
                        <h2 class="card-header card-title">Description</h2>
                        <div class="px-5 py-5 sm:px-6">
                            @if ($event->description)
                                <p class="max-w-prose whitespace-pre-line text-[15px] leading-7 text-slate-700">{{ $event->description }}</p>
                            @else
                                <p class="text-sm text-slate-500">No description for this event.</p>
                            @endif
                        </div>
                    </section>

                    @if ($event->isMeeting() || $event->meetingNotes->isNotEmpty())
                        <section class="card">
                            <div class="card-header">
                                <div class="flex items-center gap-2">
                                    <h2 class="card-title">Meeting notes</h2>
                                    <span class="badge badge-slate">{{ $event->meetingNotes->count() }}</span>
                                </div>
                                @if ($event->isMeeting())
                                    <a href="{{ route('meeting-notes.create', ['event' => $event->id]) }}" class="btn-secondary btn-sm">
                                        <x-icon name="plus" variant="outline" class="h-4 w-4" />Write meeting note
                                    </a>
                                @endif
                            </div>
                            @if ($event->meetingNotes->isEmpty())
                                <p class="px-5 py-5 text-sm text-slate-500 sm:px-6">No notes from this meeting yet.</p>
                            @else
                                <ul class="divide-y divide-slate-100">
                                    @foreach ($event->meetingNotes as $note)
                                        <li>
                                            <a href="{{ route('meeting-notes.show', $note) }}" class="group block px-5 py-4 transition-colors hover:bg-slate-50/70 sm:px-6">
                                                <div class="flex items-baseline justify-between gap-3">
                                                    <span class="text-sm font-medium text-slate-800 group-hover:text-brand-700">{{ $note->title }}</span>
                                                    <span class="shrink-0 text-xs text-slate-500">{{ $note->meeting_date->format('M j, Y') }}</span>
                                                </div>
                                                <p class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                                                    <x-meeting-note-type :note="$note" />
                                                    <span>{{ $note->author->name ?? 'Unknown' }}</span>
                                                </p>
                                                <p class="mt-1.5 text-sm text-slate-600">{{ Str::limit(trim(strip_tags($note->body)), 120) }}</p>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </section>
                    @endif
                </div>

                {{-- Details --}}
                <aside class="space-y-4">
                    <section class="card">
                        <h2 class="card-header card-title">Details</h2>
                        <dl class="divide-y divide-slate-100 text-sm">
                            <div class="flex items-start justify-between gap-3 px-5 py-3">
                                <dt class="text-slate-500">When</dt>
                                <dd class="min-w-0 text-right text-slate-800">
                                    {{ $event->starts_at->format('D, M j, Y') }}
                                    @if ($event->isMultiDay()) – {{ $event->endOrStart()->format('D, M j, Y') }} @endif
                                    <span class="block text-xs text-slate-500">{{ $event->timeLabel() }}</span>
                                </dd>
                            </div>
                            @if ($event->location)
                                <div class="flex items-start justify-between gap-3 px-5 py-3">
                                    <dt class="text-slate-500">Location</dt>
                                    <dd class="min-w-0 break-words text-right text-slate-800">{{ $event->location }}</dd>
                                </div>
                            @endif
                            @if ($event->release)
                                <div class="flex items-center justify-between gap-3 px-5 py-3">
                                    <dt class="text-slate-500">Release</dt>
                                    <dd class="min-w-0 truncate text-right">
                                        <a href="{{ route('releases.show', $event->release) }}" class="font-medium text-brand-700 hover:underline">{{ $event->release->name }}</a>
                                    </dd>
                                </div>
                            @endif
                            <div class="flex items-center justify-between gap-3 px-5 py-3">
                                <dt class="text-slate-500">Created by</dt>
                                <dd class="flex min-w-0 items-center gap-1.5 text-slate-800">
                                    <x-avatar :user="$event->creator" :name="$event->creator->name ?? '?'" size="xs" />
                                    <span class="truncate">{{ $event->creator->name ?? 'Unknown' }}</span>@include('partials.user-tag', ['tagUser' => $event->creator])
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section class="card">
                        <div class="card-header">
                            <h2 class="card-title">Attendees</h2>
                            <span class="badge badge-slate">{{ $event->attendees->count() }}</span>
                        </div>
                        @if ($event->attendees->isEmpty())
                            <p class="px-5 py-4 text-sm text-slate-500">No attendees added.</p>
                        @else
                            <ul class="space-y-1 p-2">
                                @foreach ($event->attendees as $attendee)
                                    <li class="flex items-center gap-2.5 rounded-lg px-3 py-1.5 text-sm text-slate-700">
                                        <x-avatar :user="$attendee" size="sm" />
                                        <span class="truncate">{{ $attendee->name }}</span>@include('partials.user-tag', ['tagUser' => $attendee])
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </aside>
            </div>
        </div>
    </div>

    @can('update', $event)
        @include('events._drawer', ['autoOpen' => $openEdit])
    @endcan
</x-app-layout>
